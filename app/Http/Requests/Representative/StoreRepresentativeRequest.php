<?php

namespace App\Http\Requests\Representative;

use App\Models\Representative;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique((new Representative)->getTable(), 'email')
                    ->where('user_id', $this->user('api')->getAuthIdentifier()),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
