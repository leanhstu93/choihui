<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\HuiGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * Sửa thông tin mô tả của dây. Trạng thái (bể, kết thúc) đổi qua Action riêng. Mệnh giá,
 * số tay và ngày bắt đầu quyết định lịch kỳ và dòng tiền nên không sửa qua request này.
 */
class UpdateHuiGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('group')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesFor($this->route('group'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function makeValidator(array $data, HuiGroup $group): ValidatorContract
    {
        return ValidatorFactory::make($data, self::rulesFor($group));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(HuiGroup $group): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'due_offset_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'host_name' => ['required', 'string', 'max:255'],
            'host_phone' => ['nullable', 'string', 'max:20'],
            'commission_amount' => ['sometimes', 'integer', 'min:0', 'max:'.$group->face_value * ($group->total_slots - 1)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
