<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user('api')->getAuthIdentifier();

        return [
            'date' => ['sometimes', 'required', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items' => ['sometimes', 'required', 'array', 'min:2'],
            'items.*.product_id' => [
                'required_with:items',
                'integer',
                'distinct',
                Rule::exists('store', 'id')->where('user_id', $userId),
            ],
            'items.*.quantity_change' => ['required_with:items', 'integer', 'not_in:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('items') || ! is_array($this->input('items'))) {
                return;
            }

            $changes = collect($this->input('items'))
                ->pluck('quantity_change')
                ->filter(fn ($change) => is_numeric($change))
                ->map(fn ($change) => (int) $change);

            if ($changes->sum() !== 0) {
                $validator->errors()->add('items', 'The total quantity change must equal zero.');
            }

            if (! $changes->contains(fn ($change) => $change < 0)
                || ! $changes->contains(fn ($change) => $change > 0)) {
                $validator->errors()->add('items', 'The adjustment must include both a decrease and an increase.');
            }
        });
    }
}
