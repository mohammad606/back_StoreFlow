<?php

namespace App\Http\Requests\Representative;

use App\Models\Representative;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:191',
                Rule::unique((new Representative)->getTable(), 'email')
                    ->where('user_id', $this->user('api')->getAuthIdentifier())
                    ->ignore($this->route('id')),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ];
    }
}
