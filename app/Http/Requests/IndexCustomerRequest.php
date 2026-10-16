<?php

namespace App\Http\Requests;

class IndexCustomerRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'phone', 'created_at'];
    }
}
