<?php

namespace App\Http\Requests;

class IndexRepresentativeCustomersRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'phone'];
    }
}
