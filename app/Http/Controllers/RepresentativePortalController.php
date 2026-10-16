<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\IndexRepresentativeCustomersRequest;
use App\Http\Requests\IndexRepresentativeInventoryRequest;
use App\Http\Requests\Representative\StoreRepresentativeInvoiceRequest;
use App\Services\RepresentativeInvoiceService;
use App\Services\RepresentativePortalService;
use Illuminate\Support\Facades\Auth;

class RepresentativePortalController extends Controller
{
    public function __construct(
        protected RepresentativePortalService $portalService,
        protected RepresentativeInvoiceService $invoiceService
    ) {}

    public function inventory(IndexRepresentativeInventoryRequest $request)
    {
        $filters = array_merge([
            'sort_by' => 'name',
            'sort_order' => 'asc',
            'per_page' => 20,
        ], $request->validated());

        return ApiResponse::success(
            $this->portalService->getInventory($filters),
            'Inventory retrieved successfully'
        );
    }

    public function customers(IndexRepresentativeCustomersRequest $request)
    {
        $filters = array_merge([
            'sort_by' => 'name',
            'sort_order' => 'asc',
            'per_page' => 20,
        ], $request->validated());

        return ApiResponse::success(
            $this->portalService->getCustomers($filters),
            'Customers retrieved successfully'
        );
    }

    public function invoices()
    {
        return ApiResponse::success(
            $this->invoiceService->getForRepresentative(),
            'Representative invoices retrieved successfully'
        );
    }

    public function storeInvoice(StoreRepresentativeInvoiceRequest $request)
    {
        $representative = Auth::guard('representative-api')->user();
        $invoice = $this->invoiceService->create(
            $representative,
            $request->validated()
        );

        return ApiResponse::success(
            $invoice,
            'Invoice submitted for warehouse approval'
        );
    }
}
