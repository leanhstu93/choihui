<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\HolidayBreakKind;
use App\Queries\YearCalendar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Nhập lịch nghỉ lễ chính thức của một đợt (màn quản trị Lịch nghỉ lễ).
 */
class SaveHolidayBreakRequest extends FormRequest
{
    public const MAX_DAYS = 16;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data): ValidatorContract
    {
        $validator = ValidatorFactory::make($data, self::rulesFor(), [
            'ends_on.after_or_equal' => 'Ngày cuối phải từ ngày bắt đầu trở đi.',
        ], [
            'starts_on' => 'ngày bắt đầu nghỉ',
            'ends_on' => 'ngày đi làm lại trước đó (ngày nghỉ cuối)',
            'make_up_on' => 'ngày làm bù',
            'source' => 'nguồn',
        ]);

        $validator->after(function ($validator) use ($data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $days = (int) ((strtotime((string) $data['ends_on']) - strtotime((string) $data['starts_on'])) / 86400) + 1;
            if ($days > self::MAX_DAYS) {
                $validator->errors()->add('ends_on', 'Một đợt nghỉ không quá '.self::MAX_DAYS.' ngày.');
            }
        });

        return $validator;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(): array
    {
        return [
            'year' => ['required', 'integer', 'between:'.YearCalendar::MIN_YEAR.','.YearCalendar::MAX_YEAR],
            'kind' => ['required', Rule::enum(HolidayBreakKind::class)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'make_up_on' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
