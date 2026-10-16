<?php

namespace App\Http\Requests\Representative;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepresentativeInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $representative = $this->user('representative-api');
        $ownerId = $representative->user_id;

        return [
            'date' => ['required', 'date'],
            'noa' => ['required', 'string', 'max:50'],
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('user_id', $ownerId),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('store', 'id')->where('user_id', $ownerId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
