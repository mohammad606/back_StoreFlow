<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'sender' => 'sometimes|string|max:50',
            'noa' => 'sometimes|string|max:50',
            'customer_id' => [
                'sometimes',
                Rule::exists('customers', 'id')->where('user_id', $this->user('api')->getAuthIdentifier()),
            ],
            'customer_name' => 'sometimes|string|max:50',
            'deleted_items' => 'sometimes|array',
            'deleted_items.*' => 'integer|exists:order_items,id',
            'new_items' => 'sometimes|array',
            'new_items.*.product_id' => [
                'required',
                Rule::exists('store', 'id')->where('user_id', $this->user('api')->getAuthIdentifier()),
            ],
            'new_items.*.quantity' => 'required|integer|min:1',
            'updated_items' => 'sometimes|array',
            'updated_items.*.id' => 'required|integer|exists:order_items,id',
            'updated_items.*.product_id' => [
                'sometimes',
                Rule::exists('store', 'id')->where('user_id', $this->user('api')->getAuthIdentifier()),
            ],
            'updated_items.*.quantity' => 'sometimes|integer|min:1',
        ];
    }
}
