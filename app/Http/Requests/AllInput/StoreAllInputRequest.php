<?php

namespace App\Http\Requests\AllInput;

use Illuminate\Foundation\Http\FormRequest;

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
            'type' => 'required|in:production,return',
            'noa' => 'required|string|max:50',
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:store,id',
            'items.*.name' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}

