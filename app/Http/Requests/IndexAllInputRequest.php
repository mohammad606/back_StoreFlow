<?php

namespace App\Http\Requests;

class IndexAllInputRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'date', 'noa', 'type', 'customer_name', 'created_at'];
    }
}
