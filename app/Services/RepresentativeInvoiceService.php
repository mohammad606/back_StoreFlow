<?php

namespace App\Services;

use App\Enums\RepresentativeInvoiceStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Representative;
use App\Models\RepresentativeInvoice;
use App\Models\Store;
use App\Models\StoreQuantityHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepresentativeInvoiceService
{
    public function getForRepresentative(): LengthAwarePaginator
    {
        $representative = $this->currentRepresentative();

        return RepresentativeInvoice::query()
            ->with(['customer', 'items.product'])
            ->where('representative_id', $representative->id)
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function create(Representative $representative, array $data): RepresentativeInvoice
    {
        return DB::transaction(function () use ($representative, $data): RepresentativeInvoice {
            $customer = Customer::where('user_id', $representative->user_id)
                ->findOrFail($data['customer_id']);

            $items = collect($data['items']);
            $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $products = Store::where('user_id', $representative->user_id)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            if ($products->count() !== count(array_unique($productIds))) {
                throw (new ModelNotFoundException)->setModel(Store::class);
            }

            $invoice = RepresentativeInvoice::create([
                'user_id' => $representative->user_id,
                'representative_id' => $representative->id,
                'customer_id' => $customer->id,
                'date' => $data['date'],
                'noa' => $data['noa'],
                'status' => RepresentativeInvoiceStatusEnum::Pending,
            ]);

            $invoice->items()->createMany(
                $items->map(fn ($item) => [
                    'store_id' => (int) $item['product_id'],
                    'name' => $products[(int) $item['product_id']]->name,
                    'quantity' => (int) $item['quantity'],
                ])->all()
            );

            return $invoice->load(['customer', 'items.product']);
        });
    }

    public function getForOwner(array $filters): LengthAwarePaginator
    {
        $userId = $this->currentOwnerId();
        $query = RepresentativeInvoice::query()
            ->with(['representative:id,user_id,name,email', 'customer', 'items.product'])
            ->where('user_id', $userId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $query) use ($search): void {
                $query->where('noa', 'like', "%{$search}%")
                    ->orWhereHas('representative', function (Builder $representativeQuery) use ($search): void {
                        $representativeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function approve(int $invoiceId): RepresentativeInvoice
    {
        $userId = $this->currentOwnerId();

        return DB::transaction(function () use ($invoiceId, $userId): RepresentativeInvoice {
            $invoice = RepresentativeInvoice::where('user_id', $userId)
                ->with(['items', 'customer', 'representative'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $this->ensurePending($invoice);

            $productIds = $invoice->items->pluck('store_id')->map(fn ($id) => (int) $id)->all();
            $products = Store::where('user_id', $userId)
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count(array_unique($productIds))) {
                throw (new ModelNotFoundException)->setModel(Store::class);
            }

            foreach ($invoice->items as $item) {
                $product = $products[$item->store_id];

                if ($product->quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for product: {$product->name}",
                    ]);
                }
            }

            $order = Order::create([
                'user_id' => $userId,
                'date' => $invoice->date,
                'sender' => $invoice->representative->name,
                'noa' => $invoice->noa,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer->name,
            ]);

            foreach ($invoice->items as $item) {
                $product = $products[$item->store_id];
                $product->quantity -= $item->quantity;
                $product->save();

                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $item->quantity,
                ]);

                StoreQuantityHistory::create([
                    'store_id' => $product->id,
                    'quantity' => $product->quantity,
                    'change_amount' => -$item->quantity,
                    'change_type' => StockMovementTypeEnum::Order,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'user_id' => $userId,
                    'notes' => 'Sold from representative invoice #'.$invoice->id,
                ]);
            }

            $invoice->status = RepresentativeInvoiceStatusEnum::Approved;
            $invoice->order_id = $order->id;
            $invoice->save();

            return $invoice->load(['customer', 'representative', 'items.product', 'order.items']);
        });
    }

    public function reject(int $invoiceId, ?string $decisionNote = null): RepresentativeInvoice
    {
        $userId = $this->currentOwnerId();

        return DB::transaction(function () use ($invoiceId, $userId, $decisionNote): RepresentativeInvoice {
            $invoice = RepresentativeInvoice::where('user_id', $userId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $this->ensurePending($invoice);
            $invoice->status = RepresentativeInvoiceStatusEnum::Rejected;
            $invoice->decision_note = $decisionNote;
            $invoice->save();

            return $invoice->load(['customer', 'representative', 'items.product']);
        });
    }

    private function ensurePending(RepresentativeInvoice $invoice): void
    {
        if ($invoice->status !== RepresentativeInvoiceStatusEnum::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending invoices can be reviewed.',
            ]);
        }
    }

    private function currentRepresentative(): Representative
    {
        $representative = Auth::guard('representative-api')->user();

        abort_unless($representative instanceof Representative, 401, 'Unauthenticated.');

        return $representative;
    }

    private function currentOwnerId(): int
    {
        $userId = Auth::guard('api')->id();

        if ($userId === null) {
            abort(401, 'Unauthenticated.');
        }

        return (int) $userId;
    }
}
