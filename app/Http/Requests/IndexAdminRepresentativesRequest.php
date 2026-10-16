<?php

namespace App\Http\Requests;

class IndexAdminRepresentativesRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'email', 'user_id', 'created_at'];
    }
}
