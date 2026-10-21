<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\WinnerType;
use App\Models\HuiRound;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateHuiRoundRequest extends FormRequest
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
        return self::rulesFor($this->route('round'), $this->user());
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
    public static function makeValidator(array $data, HuiRound $round, User $user): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($round, $user), self::messageList())
            ->after(fn (Validator $validator) => self::checkBusinessRules($validator, $data));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(HuiRound $round, User $user): array
    {
        return [
            'bid_amount' => ['nullable', 'integer', 'min:0', 'max:'.$round->group->face_value],
            'winner_type' => ['required', Rule::enum(WinnerType::class)],
            'winner_slot_id' => [
                'nullable',
                'required_if:winner_type,'.WinnerType::Minh->value,
                'prohibited_unless:winner_type,'.WinnerType::Minh->value,
                'ulid',
                Rule::exists('my_slots', 'id')
                    ->where('user_id', $user->id)
                    ->where('group_id', $round->group_id),
                Rule::unique('hui_rounds', 'winner_slot_id')->ignore($round->id),
            ],
            'winner_name' => ['nullable', 'prohibited_unless:winner_type,'.WinnerType::NguoiKhac->value, 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function checkBusinessRules(Validator $validator, array $data): void
    {
        $winnerType = WinnerType::tryFrom((string) ($data['winner_type'] ?? ''));

        if ($winnerType !== null && $winnerType !== WinnerType::ChuaCo && ($data['bid_amount'] ?? null) === null) {
            $validator->errors()->add('bid_amount', 'Cần nhập lãi thăm trước khi chọn người hốt.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'bid_amount.max' => 'Lãi thăm không được vượt quá mệnh giá.',
            'winner_slot_id.required_if' => 'Vui lòng chọn tay hốt.',
            'winner_slot_id.exists' => 'Tay hốt không thuộc dây này.',
            'winner_slot_id.unique' => 'Tay này đã hốt ở một kỳ khác.',
        ];
    }
}
