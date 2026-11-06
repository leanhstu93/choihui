<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GoldType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Nhập giá hiện tại của một loại vàng (document/06-vang.md VG-06).
 */
class UpdateGoldPriceRequest extends FormRequest
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
            'gold_type' => ['required', Rule::enum(GoldType::class)],
            'price_per_chi' => ['required', 'integer', 'min:1', 'max:10000000000'],
        ];
    }
}
