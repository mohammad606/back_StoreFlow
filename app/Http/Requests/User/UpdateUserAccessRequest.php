<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatusEnum;
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
            'status' => ['required', 'string', Rule::in(UserStatusEnum::values())],
            'subscription_starts_at' => [
                'nullable',
                'required_if:status,' . UserStatusEnum::Active->value,
                'date',
                'date_format:Y-m-d',
                Rule::excludeIf(fn() => $this->input('status') !== UserStatusEnum::Active->value)
            ],
            'subscription_ends_at' => [
                'nullable',
                'required_if:status,' . UserStatusEnum::Active->value,
                'date',
                'date_format:Y-m-d',
                'after_or_equal:subscription_starts_at',
                Rule::excludeIf(fn() => $this->input('status') !== UserStatusEnum::Active->value),
            ],
            'trial_ends_at' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after:now',
                Rule::excludeIf(fn() => $this->input('status') !== UserStatusEnum::FreeTrial->value)
            ]
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->string('status') == UserStatusEnum::FreeTrial->value) {
            $this->merge([
                'trial_ends_at' => now()->addDays(7)->format('Y-m-d')
            ]);
        }
    }
}
