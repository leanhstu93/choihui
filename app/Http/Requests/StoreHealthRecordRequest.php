<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\HealthRecordKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Thêm hoặc sửa một lần khám (document/09-gia-dinh.md GD-06).
 */
class StoreHealthRecordRequest extends FormRequest
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
        return self::rulesFor();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor(), [], [
            'visited_on' => 'ngày khám',
            'kind' => 'loại',
            'title' => 'nội dung',
            'place' => 'nơi khám',
            'amount' => 'chi phí',
            'follow_up_on' => 'ngày tái khám',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(): array
    {
        return [
            'visited_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'kind' => ['required', Rule::enum(HealthRecordKind::class)],
            'title' => ['required', 'string', 'max:150'],
            'place' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'follow_up_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:visited_on'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
