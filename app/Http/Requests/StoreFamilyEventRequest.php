<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\FamilyCalendar;
use App\Enums\CalendarType;
use App\Enums\FamilyEventKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Thêm hoặc sửa một ngày nhớ (document/09-gia-dinh.md GD-01).
 */
class StoreFamilyEventRequest extends FormRequest
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
        return self::rulesFor($this->input('calendar'), $this->input('month'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($data['calendar'] ?? null, $data['month'] ?? null), [], [
            'title' => 'tên',
            'kind' => 'loại',
            'calendar' => 'lịch',
            'day' => 'ngày',
            'month' => 'tháng',
            'origin_year' => 'năm gốc',
            'remind_days_before' => 'số ngày nhắc trước',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(mixed $calendar, mixed $month): array
    {
        $lunar = $calendar === CalendarType::Am->value;

        return [
            'title' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(FamilyEventKind::class)],
            'calendar' => ['required', Rule::enum(CalendarType::class)],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'day' => ['required', 'integer', 'min:1', 'max:'.($lunar ? 30 : 31), function (string $attribute, mixed $value, Closure $fail) use ($lunar, $month): void {
                if (is_int($value) && is_int($month) && $month >= 1 && $month <= 12 && ! FamilyCalendar::isValid($lunar, $value, $month)) {
                    $fail("Tháng {$month} không có ngày {$value}.");
                }
            }],
            'origin_year' => ['nullable', 'integer', 'min:1800', 'max:2200'],
            'remind_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
