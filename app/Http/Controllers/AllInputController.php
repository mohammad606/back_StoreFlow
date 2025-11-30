<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\AllInput\StoreAllInputRequest;
use App\Http\Requests\AllInput\UpdateAllInputRequest;
use App\Services\AllInputService;
use App\Helpers\ApiResponse;

class AllInputController extends Controller
{
    public function __construct(
        protected AllInputService $allInputService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'sortBy' => $request->get('sortBy', 'date'),
                'sortOrder' => $request->get('sortOrder', 'desc'),
                'perPage' => $request->get('perPage', 20),
            ];

            $invoices = $this->allInputService->getAll($filters);

            return ApiResponse::success($invoices->items(), 'Invoices retrieved successfully', $invoices);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $invoice = $this->allInputService->getById($id);

            return ApiResponse::success($invoice, 'Invoice retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Invoice not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function store(StoreAllInputRequest $request)
    {
        try {
            $invoice = $this->allInputService->create($request->validated());

            return ApiResponse::success($invoice, 'Invoice created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function update(UpdateAllInputRequest $request, $id)
    {
        try {
            $invoice = $this->allInputService->update($id, $request->validated());

            return ApiResponse::success($invoice, 'Invoice updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Invoice not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->allInputService->delete($id);

            return ApiResponse::success([], 'Invoice deleted successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Invoice not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function getInvoiceById($id)
    {
        try {
            $invoice = $this->allInputService->getById($id);

            return ApiResponse::success($invoice, 'Invoice retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Invoice not found', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
