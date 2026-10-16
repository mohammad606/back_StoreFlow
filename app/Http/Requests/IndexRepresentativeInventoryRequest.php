<?php

namespace App\Http\Requests;

class IndexRepresentativeInventoryRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'quantity'];
    }
}
