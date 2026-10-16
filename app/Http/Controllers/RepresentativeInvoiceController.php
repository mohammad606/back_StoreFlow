<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\IndexRepresentativeInvoicesRequest;
use App\Http\Requests\Representative\RejectRepresentativeInvoiceRequest;
use App\Services\RepresentativeInvoiceService;

class RepresentativeInvoiceController extends Controller
{
    public function __construct(
        protected RepresentativeInvoiceService $invoiceService
    ) {}

    public function index(IndexRepresentativeInvoicesRequest $request)
    {
        $filters = array_merge([
            'sort_by' => 'id',
            'sort_order' => 'desc',
            'per_page' => 20,
        ], $request->validated());

        return ApiResponse::success(
            $this->invoiceService->getForOwner($filters),
            'Representative invoices retrieved successfully'
        );
    }

    public function approve(int $id)
    {
        return ApiResponse::success(
            $this->invoiceService->approve($id),
            'Representative invoice approved and added to orders'
        );
    }

    public function reject(RejectRepresentativeInvoiceRequest $request, int $id)
    {
        return ApiResponse::success(
            $this->invoiceService->reject($id, $request->validated('decision_note')),
            'Representative invoice rejected'
        );
    }
}
