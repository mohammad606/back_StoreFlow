<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user('api')->getAuthIdentifier();

        return [
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('store', 'id')->where('user_id', $userId),
            ],
            'items.*.quantity_change' => ['required', 'integer', 'not_in:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items');

            if (!is_array($items)) {
                return;
            }

            $changes = collect($items)
                ->pluck('quantity_change')
                ->filter(fn($change) => is_numeric($change))
                ->map(fn($change) => (int)$change);

            if ($changes->sum() !== 0) {
                $validator->errors()->add('items', 'The total quantity change must equal zero.');
            }

            if (!$changes->contains(fn($change) => $change < 0)
                || !$changes->contains(fn($change) => $change > 0)) {
                $validator->errors()->add('items', 'The adjustment must include both a decrease and an increase.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'The selected product is invalid or does not belong to the authenticated user.',
        ];
    }
}
