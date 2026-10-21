<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * Ghi nhận số tiền thực đóng hoặc thực nhận (cho phép thiếu hoặc muộn). Trạng thái do
 * Action tự xác định từ số tiền và ngày.
 */
class UpdateCashEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('cashEntry')) ?? false;
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
        return ValidatorFactory::make($data, self::rulesFor());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(): array
    {
        return [
            'amount_actual' => ['required', 'integer', 'min:0'],
            'paid_date' => ['nullable', 'required_unless:amount_actual,0', 'date'],
            'written_off' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
