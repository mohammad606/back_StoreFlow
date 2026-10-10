<?php

namespace App\Http\Requests\User;

use App\AdminAccessEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(AdminAccessEnum::values())],
            'subscription_starts_at' => [
                'nullable',
                'required_if:status,' . AdminAccessEnum::ACTIVE->value,
                'date',
                'date_format:Y-m-d',
                Rule::excludeIf(fn() => $this->input('status') !== AdminAccessEnum::ACTIVE->value)
            ],
            'subscription_ends_at' => [
                'nullable',
                'required_if:status,' . AdminAccessEnum::ACTIVE->value,
                'date',
                'date_format:Y-m-d',
                'after_or_equal:subscription_starts_at',
                Rule::excludeIf(fn() => $this->input('status') !== AdminAccessEnum::ACTIVE->value),
            ],
            'trial_ends_at' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after:now',
                Rule::excludeIf(fn() => $this->input('status') !== AdminAccessEnum::FREE_TRIAL->value)
            ]
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->string('status') == AdminAccessEnum::FREE_TRIAL->value) {
            $this->merge([
                'trial_ends_at' => now()->addDays(7)->format('Y-m-d')
            ]);
        }
    }
}
