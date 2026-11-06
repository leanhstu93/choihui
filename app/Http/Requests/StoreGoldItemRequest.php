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
 * Thêm hoặc sửa một món vàng (document/06-vang.md VG-01, VG-02).
 */
class StoreGoldItemRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:100'],
            'weight_ly' => ['required', 'integer', 'min:1', 'max:1000000'],
            'cost_amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'bought_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shop' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
