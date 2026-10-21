<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\HuiRound;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Kết quả một kỳ của dây chủ hụi (CH-04): lãi thăm và tay hốt. Tay hốt phải là
 * tay của chính dây này và của chính người dùng.
 */
class UpdateHostRoundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('round')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var HuiRound $round */
        $round = $this->route('round');

        return self::rulesFor($round, $this->user());
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
    public static function makeValidator(array $data, HuiRound $round, User $user): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($round, $user), self::messageList());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(HuiRound $round, User $user): array
    {
        return [
            'bid_amount' => ['nullable', 'required_with:winner_host_slot_id', 'integer', 'min:0', 'max:'.$round->group->face_value],
            'winner_host_slot_id' => [
                'nullable',
                'ulid',
                Rule::exists('host_slots', 'id')
                    ->where('user_id', $user->id)
                    ->where('group_id', $round->group_id),
                Rule::unique('hui_rounds', 'winner_host_slot_id')->ignore($round->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'bid_amount.required_with' => 'Cần nhập lãi thăm trước khi chọn tay hốt.',
            'winner_host_slot_id.exists' => 'Tay hốt không thuộc dây này.',
            'winner_host_slot_id.unique' => 'Tay này đã hốt ở kỳ khác.',
        ];
    }
}
