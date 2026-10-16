<?php

namespace App\Http\Requests;

class IndexStoreRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'quantity', 'created_at'];
    }
}
