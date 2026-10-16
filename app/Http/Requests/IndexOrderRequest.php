<?php

namespace App\Http\Requests;

class IndexOrderRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'date', 'noa', 'sender', 'customer_name', 'created_at'];
    }
}
