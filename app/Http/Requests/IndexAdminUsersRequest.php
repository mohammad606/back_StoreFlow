<?php

namespace App\Http\Requests;

class IndexAdminUsersRequest extends IndexQueryRequest
{
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'email', 'role', 'status', 'created_at'];
    }
}
