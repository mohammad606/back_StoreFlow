<?php

namespace App\Http\Requests;

class IndexInventoryAdjustmentsRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['date', 'id', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }
}
