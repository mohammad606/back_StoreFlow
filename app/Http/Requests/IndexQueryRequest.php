<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class IndexQueryRequest extends FormRequest
{
    abstract protected function allowedSortColumns(): array;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $aliases = [
            'sort_by' => 'sortBy',
            'sort_order' => 'sortOrder',
            'per_page' => 'perPage',
        ];
        $filters = [];

        foreach ($aliases as $canonical => $legacy) {
            if (! $this->exists($canonical) && $this->exists($legacy)) {
                $filters[$canonical] = $this->input($legacy);
            }
        }

        if ($filters !== []) {
            $this->merge($filters);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in($this->allowedSortColumns())],
            'sort_order' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
