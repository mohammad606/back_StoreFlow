<?php

namespace App\Http\Requests\Representative;

use Illuminate\Foundation\Http\FormRequest;

class LoginRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_user_id' => ['required', 'integer', 'exists:users,id'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
