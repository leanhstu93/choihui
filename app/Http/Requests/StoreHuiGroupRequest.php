<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\HuiGroupType;
use App\Models\HuiGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tạo dây hụi kèm các tay của mình. Livewire dùng cùng bộ rule qua makeValidator().
 */
class StoreHuiGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', HuiGroup::class) ?? false;
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
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => self::checkBusinessRules($validator, $this->all())];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor())
            ->after(fn (Validator $validator) => self::checkBusinessRules($validator, $data));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(HuiGroupType::class)],
            'face_value' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'total_slots' => ['required', 'integer', 'min:2', 'max:200'],
            'start_date' => ['required', 'date'],
            'due_offset_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'host_name' => ['required', 'string', 'max:255'],
            'host_phone' => ['nullable', 'string', 'max:20'],
            'commission_amount' => ['sometimes', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*' => ['required', 'string', 'max:50', 'distinct'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function checkBusinessRules(Validator $validator, array $data): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (count($data['slots']) > (int) $data['total_slots']) {
            $validator->errors()->add('slots', 'Số tay của mình không được vượt quá tổng số tay của dây.');
        }

        if ((int) ($data['commission_amount'] ?? 0) > (int) $data['face_value'] * ((int) $data['total_slots'] - 1)) {
            $validator->errors()->add('commission_amount', 'Tiền thảo không được lớn hơn số tiền hốt được.');
        }
    }
}
