<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRepresentativesLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'representatives_limit' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
