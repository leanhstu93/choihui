<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * Ảnh tải lên cho món vàng và lần sửa (document/06-vang.md V13, 08-sua-chua.md S5). Chỉ
 * nhận JPG, PNG, WEBP; không nhận SVG vì SVG có thể chứa script.
 */
class StorePhotosRequest extends FormRequest
{
    public const MAX_KILOBYTES = 8192;

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
        return self::rulesFor(0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data, int $remainingSlots, int $limit): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($remainingSlots), [
            'photos.max' => "Tối đa {$limit} ảnh.",
            'photos.*.image' => 'Chỉ nhận file ảnh JPG, PNG hoặc WEBP.',
            'photos.*.mimes' => 'Chỉ nhận file ảnh JPG, PNG hoặc WEBP.',
            'photos.*.max' => 'Mỗi ảnh tối đa 8 MB.',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(int $remainingSlots): array
    {
        return [
            'photos' => ['array', 'max:'.$remainingSlots],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}
