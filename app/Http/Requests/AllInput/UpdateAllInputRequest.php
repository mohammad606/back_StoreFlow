<?php

namespace App\Http\Requests\AllInput;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'type' => 'sometimes|in:production,return',
            'noa' => 'sometimes|string|max:50',
            'customer_id' => 'sometimes|exists:customers,id',
            'deleted_items' => 'sometimes|array',
            'deleted_items.*' => 'integer|exists:all_input_items,id',
            'new_items' => 'sometimes|array',
            'new_items.*.product_id' => 'required|exists:store,id',
            'new_items.*.quantity' => 'required|integer|min:1',
            'updated_items' => 'sometimes|array',
            'updated_items.*.id' => 'required|integer|exists:all_input_items,id',
            'updated_items.*.product_id' => 'sometimes|exists:store,id',
            'updated_items.*.quantity' => 'sometimes|integer|min:1',
        ];
    }
}

