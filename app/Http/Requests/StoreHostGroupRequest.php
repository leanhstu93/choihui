<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tạo dây chủ hụi (CH-01, CH-03): thông tin dây và hụi viên giữ từng tay 1..N.
 * Mọi hụi viên phải thuộc danh bạ của chính người dùng.
 */
class StoreHostGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isHost() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesFor($this->user());
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => self::checkBusinessRules($validator, $this->all())];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageList();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data, User $user): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($user), self::messageList())
            ->after(fn (Validator $validator) => self::checkBusinessRules($validator, $data));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'face_value' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'total_slots' => ['required', 'integer', 'min:2', 'max:200'],
            'start_date' => ['required', 'date'],
            'due_offset_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'commission_amount' => ['sometimes', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'member_ids' => ['required', 'array'],
            'member_ids.*' => ['required', 'ulid', Rule::exists('members', 'id')->where('user_id', $user->id)],
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

        if (count($data['member_ids']) !== (int) $data['total_slots']) {
            $validator->errors()->add('member_ids', "Cần chọn hụi viên cho đủ {$data['total_slots']} tay.");
        }

        if (($data['commission_amount'] ?? 0) > $data['face_value'] * ($data['total_slots'] - 1)) {
            $validator->errors()->add('commission_amount', 'Tiền thảo lớn hơn số tiền hốt được.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'member_ids.required' => 'Cần chọn hụi viên cho từng tay.',
            'member_ids.*.required' => 'Chọn hụi viên giữ tay này.',
            'member_ids.*.exists' => 'Hụi viên không tồn tại.',
        ];
    }
}
