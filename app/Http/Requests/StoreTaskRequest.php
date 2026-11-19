<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskRepeat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Thêm hoặc sửa một việc (document/10-viec-can-lam.md V2, V6, VL-02).
 */
class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesFor($this->input('recurrence'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($data['recurrence'] ?? null), [
            'due_on.required_unless' => 'Việc lặp lại cần có ngày bắt đầu.',
            'due_time.prohibited_if' => 'Chọn ngày trước rồi mới chọn giờ.',
            'repeat_weekdays.min' => 'Chọn ít nhất một thứ trong tuần.',
        ], [
            'title' => 'tên việc',
            'note' => 'ghi chú',
            'due_on' => 'ngày',
            'due_time' => 'giờ',
            'recurrence' => 'lặp lại',
            'repeat_weekdays' => 'thứ trong tuần',
            'repeat_day' => 'ngày lặp',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(mixed $recurrence): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01', 'required_unless:recurrence,'.TaskRepeat::None->value],
            'due_time' => ['nullable', 'date_format:H:i', 'prohibited_if:due_on,null'],
            'important' => ['boolean'],
            'recurrence' => ['required', Rule::enum(TaskRepeat::class)],
            'repeat_weekdays' => ['integer', 'min:'.($recurrence === TaskRepeat::Weekly->value ? 1 : 0), 'max:127'],
            'repeat_day' => ['nullable', 'integer', 'min:1', 'max:'.($recurrence === TaskRepeat::MonthlyLunar->value ? 30 : 31)],
        ];
    }
}
