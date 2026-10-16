<?php

namespace App\Models;

use App\Enums\RepresentativeInvoiceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepresentativeInvoice extends Model
{
    protected $fillable = [
        'user_id',
        'representative_id',
        'customer_id',
        'date',
        'noa',
        'status',
        'decision_note',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => RepresentativeInvoiceStatusEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Representative::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepresentativeInvoiceItem::class);
    }
}
