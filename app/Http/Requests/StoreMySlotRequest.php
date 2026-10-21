<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\HuiGroup;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMySlotRequest extends FormRequest
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
        return self::rulesFor($this->user(), $this->input('group_id'));
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
        return ValidatorFactory::make($data, self::rulesFor($user, $data['group_id'] ?? null), self::messageList())
            ->after(fn (Validator $validator) => self::checkBusinessRules($validator, $data));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(User $user, mixed $groupId): array
    {
        return [
            'group_id' => [
                'required',
                'ulid',
                Rule::exists('hui_groups', 'id')->where('user_id', $user->id),
            ],
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('my_slots', 'label')->where('group_id', $groupId),
            ],
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

        $group = HuiGroup::query()->withCount('slots')->findOrFail($data['group_id']);

        if ($group->slots_count >= $group->total_slots) {
            $validator->errors()->add('label', 'Số tay của mình không được vượt quá tổng số tay của dây.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'group_id.exists' => 'Dây hụi không tồn tại.',
            'label.unique' => 'Tên tay đã có trong dây này.',
        ];
    }
}
