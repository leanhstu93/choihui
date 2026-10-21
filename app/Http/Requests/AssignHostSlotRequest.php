<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Gán (hoặc đổi) hụi viên giữ một tay của dây chủ hụi (CH-03). Hụi viên phải
 * thuộc danh bạ của chính người dùng.
 */
class AssignHostSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('hostSlot')) ?? false;
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
        return ValidatorFactory::make($data, self::rulesFor($user), self::messageList());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(User $user): array
    {
        return [
            'member_id' => ['required', 'ulid', Rule::exists('members', 'id')->where('user_id', $user->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'member_id.exists' => 'Hụi viên không tồn tại.',
        ];
    }
}
