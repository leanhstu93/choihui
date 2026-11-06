<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * Bán toàn bộ hoặc một phần món vàng (document/06-vang.md VG-04).
 */
class StoreGoldSaleRequest extends FormRequest
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
        return ValidatorFactory::make($data, self::rulesFor());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(): array
    {
        return [
            'weight_ly' => ['required', 'integer', 'min:1', 'max:1000000'],
            'amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'sold_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
