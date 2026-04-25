<?php

namespace App\Http\Requests;

use App\Enums\ConferenceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in([ConferenceStatus::Active->value, ConferenceStatus::Inactive->value])],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'register_started_at' => ['required', 'date', 'before_or_equal:started_at'],
            'register_ended_at' => ['required', 'date', 'after:register_started_at', 'before_or_equal:started_at'],
        ];
    }
}
