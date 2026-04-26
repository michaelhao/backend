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

    public function attributes(): array
    {
        return [
            'name' => '說明會名稱',
            'status' => '狀態',
            'started_at' => '活動開始時間',
            'ended_at' => '活動結束時間',
            'register_started_at' => '報名開始時間',
            'register_ended_at' => '報名截止時間',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute 為必填',
            'string' => ':attribute 必須為文字',
            'max.string' => ':attribute 不可超過 :max 個字元',
            'date' => ':attribute 必須為有效的日期時間',
            'in' => ':attribute 的值不正確',
            'ended_at.after' => '活動結束時間必須晚於活動開始時間',
            'register_ended_at.after' => '報名截止時間必須晚於報名開始時間',
            'register_started_at.before_or_equal' => '報名開始時間不可晚於活動開始時間',
            'register_ended_at.before_or_equal' => '報名截止時間不可晚於活動開始時間',
        ];
    }
}
