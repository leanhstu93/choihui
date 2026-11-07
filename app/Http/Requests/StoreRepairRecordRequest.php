<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * Thêm hoặc sửa một lần sửa (document/08-sua-chua.md SC-03).
 */
class StoreRepairRecordRequest extends FormRequest
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
        return ValidatorFactory::make($data, self::rulesFor(), [], ['amount' => 'số tiền']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(): array
    {
        return [
            'repaired_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'part' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'place' => ['nullable', 'string', 'max:100'],
            'warranty_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:repaired_on'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
