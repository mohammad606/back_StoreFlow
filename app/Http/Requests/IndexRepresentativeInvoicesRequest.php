<?php

namespace App\Http\Requests;

use App\Enums\RepresentativeInvoiceStatusEnum;
use Illuminate\Validation\Rule;

class IndexRepresentativeInvoicesRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'date', 'noa', 'status', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'nullable', Rule::enum(RepresentativeInvoiceStatusEnum::class)],
        ]);
    }
}
