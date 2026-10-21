<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Models\CashEntry;
use App\Models\HuiRound;
use App\Models\MySlot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashEntry::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'round_id' => ['required', 'ulid', Rule::exists('hui_rounds', 'id')->where('user_id', $this->user()->id)],
            'slot_id' => [
                'required',
                'ulid',
                Rule::exists('my_slots', 'id')->where('user_id', $this->user()->id),
                Rule::unique('cash_entries', 'slot_id')->where('round_id', $this->input('round_id')),
            ],
            'direction' => ['required', Rule::enum(CashDirection::class)],
            'amount_expected' => ['required', 'integer', 'min:0'],
            'amount_actual' => ['sometimes', 'integer', 'min:0'],
            'paid_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::enum(CashEntryStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $roundGroupId = HuiRound::query()->whereKey($this->input('round_id'))->value('group_id');
                $slotGroupId = MySlot::query()->whereKey($this->input('slot_id'))->value('group_id');

                if ($roundGroupId !== $slotGroupId) {
                    $validator->errors()->add('slot_id', 'Tay và kỳ phải thuộc cùng một dây hụi.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'round_id.exists' => 'Kỳ hụi không tồn tại.',
            'slot_id.exists' => 'Tay hụi không tồn tại.',
            'slot_id.unique' => 'Tay này đã có dòng tiền trong kỳ này.',
        ];
    }
}
