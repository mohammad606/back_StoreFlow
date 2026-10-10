<?php

namespace App\Http\Requests\AllInput;

use App\Enums\AllInputTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'type' => ['required', Rule::in(AllInputTypeEnum::values())],
            'noa' => 'required|string|max:50',
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('user_id', $this->user('api')->getAuthIdentifier()),
            ],
            'customer_name' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('store', 'id')->where('user_id', $this->user('api')->getAuthIdentifier()),
            ],
            'items.*.name' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
