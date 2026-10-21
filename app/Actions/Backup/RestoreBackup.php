<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\HuiFrequency;
use App\Enums\HuiGroupStatus;
use App\Enums\HuiGroupType;
use App\Enums\WinnerType;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use App\Queries\UserBackup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Khôi phục dữ liệu từ file sao lưu (FR-11): THAY THẾ toàn bộ dây hụi hiện có của người dùng.
 *
 * Kiểm tra cả file trước khi xóa gì; lỗi thì dữ liệu cũ giữ nguyên. user_id luôn là người
 * dùng được truyền vào, không bao giờ lấy từ file.
 */
final class RestoreBackup
{
    /**
     * @param  array<mixed>  $data
     * @return array{groups: int, rounds: int, entries: int}
     *
     * @throws HuiActionException khi file không hợp lệ.
     */
    public function handle(User $user, array $data): array
    {
        if (Auth::check() && Auth::id() !== $user->id) {
            throw new HuiActionException('Chỉ được khôi phục vào tài khoản đang đăng nhập.');
        }

        $this->validateStructure($data);

        foreach ($data['groups'] as $index => $group) {
            $this->validateGroup($group, $index + 1);
        }

        return DB::transaction(function () use ($user, $data): array {
            $this->deleteExistingData($user);

            $stats = ['groups' => 0, 'rounds' => 0, 'entries' => 0];

            foreach ($data['groups'] as $groupData) {
                $this->restoreGroup($user, $groupData, $stats);
            }

            if (isset($data['settings']['reminder_days_before'])) {
                $user->forceFill(['reminder_days_before' => $data['settings']['reminder_days_before']])->save();
            }

            return $stats;
        });
    }

    /**
     * @param  array<mixed>  $data
     */
    private function validateStructure(array $data): void
    {
        $validator = Validator::make($data, [
            'app' => ['required', Rule::in([UserBackup::APP])],
            'version' => ['required', Rule::in([UserBackup::VERSION])],
            'settings.reminder_days_before' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'groups' => ['present', 'array', 'max:500'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.type' => ['required', Rule::enum(HuiGroupType::class)],
            'groups.*.frequency' => ['required', Rule::enum(HuiFrequency::class)],
            'groups.*.face_value' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'groups.*.total_slots' => ['required', 'integer', 'min:2', 'max:200'],
            'groups.*.start_date' => ['required', 'date_format:Y-m-d'],
            'groups.*.due_offset_days' => ['required', 'integer', 'min:0', 'max:60'],
            'groups.*.host_name' => ['required', 'string', 'max:255'],
            'groups.*.host_phone' => ['nullable', 'string', 'max:20'],
            'groups.*.commission_amount' => ['required', 'integer', 'min:0'],
            'groups.*.status' => ['required', Rule::enum(HuiGroupStatus::class)],
            'groups.*.broken_round_no' => ['nullable', 'integer'],
            'groups.*.note' => ['nullable', 'string', 'max:2000'],
            'groups.*.slots' => ['required', 'array', 'min:1'],
            'groups.*.slots.*.key' => ['required', 'string', 'max:64'],
            'groups.*.slots.*.label' => ['required', 'string', 'max:50'],
            'groups.*.rounds' => ['required', 'array'],
            'groups.*.rounds.*.round_no' => ['required', 'integer'],
            'groups.*.rounds.*.open_date' => ['required', 'date_format:Y-m-d'],
            'groups.*.rounds.*.bid_amount' => ['nullable', 'integer', 'min:0'],
            'groups.*.rounds.*.winner_type' => ['required', Rule::enum(WinnerType::class)],
            'groups.*.rounds.*.winner_slot' => ['nullable', 'string'],
            'groups.*.rounds.*.winner_name' => ['nullable', 'string', 'max:255'],
            'groups.*.rounds.*.entries' => ['present', 'array'],
            'groups.*.rounds.*.entries.*.slot' => ['required', 'string'],
            'groups.*.rounds.*.entries.*.direction' => ['required', Rule::enum(CashDirection::class)],
            'groups.*.rounds.*.entries.*.amount_expected' => ['required', 'integer', 'min:0'],
            'groups.*.rounds.*.entries.*.amount_actual' => ['required', 'integer', 'min:0'],
            'groups.*.rounds.*.entries.*.paid_date' => ['nullable', 'date_format:Y-m-d'],
            'groups.*.rounds.*.entries.*.status' => ['required', Rule::enum(CashEntryStatus::class)],
            'groups.*.rounds.*.entries.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            throw new HuiActionException('File sao lưu không hợp lệ: '.$validator->errors()->first());
        }
    }

    /**
     * Ràng buộc nghiệp vụ giống database và Form Request (document/01-dac-ta.md mục 8).
     *
     * @param  array<string, mixed>  $group
     */
    private function validateGroup(array $group, int $position): void
    {
        $fail = fn (string $message) => throw new HuiActionException("Dây thứ {$position} ({$group['name']}): {$message}");

        $slotKeys = array_column($group['slots'], 'key');
        $labels = array_column($group['slots'], 'label');
        $roundNos = array_column($group['rounds'], 'round_no');
        $sortedRoundNos = $roundNos;
        sort($sortedRoundNos);

        if (count($slotKeys) > $group['total_slots']) {
            $fail('số tay của mình vượt quá tổng số tay.');
        }

        if (count(array_unique($slotKeys)) !== count($slotKeys) || count(array_unique($labels)) !== count($labels)) {
            $fail('có tay bị trùng.');
        }

        if ($sortedRoundNos !== range(1, $group['total_slots'])) {
            $fail('danh sách kỳ phải đủ từ 1 đến '.$group['total_slots'].'.');
        }

        $isBroken = $group['status'] === HuiGroupStatus::Be->value;
        $brokenRoundNo = $group['broken_round_no'];

        if ($isBroken !== ($brokenRoundNo !== null) || ($brokenRoundNo !== null && ($brokenRoundNo < 1 || $brokenRoundNo > $group['total_slots']))) {
            $fail('kỳ bể không hợp lệ.');
        }

        $winnerSlots = [];

        foreach ($group['rounds'] as $round) {
            if ($round['bid_amount'] !== null && $round['bid_amount'] > $group['face_value']) {
                $fail("lãi thăm kỳ {$round['round_no']} lớn hơn mệnh giá.");
            }

            $isMine = $round['winner_type'] === WinnerType::Minh->value;

            if ($isMine !== ($round['winner_slot'] !== null) || ($isMine && ! in_array($round['winner_slot'], $slotKeys, true))) {
                $fail("người hốt kỳ {$round['round_no']} không hợp lệ.");
            }

            if ($isMine) {
                $winnerSlots[] = $round['winner_slot'];
            }

            $entrySlots = array_column($round['entries'], 'slot');

            if (array_diff($entrySlots, $slotKeys) !== [] || count(array_unique($entrySlots)) !== count($entrySlots)) {
                $fail("dòng tiền kỳ {$round['round_no']} không khớp với các tay.");
            }
        }

        if (count(array_unique($winnerSlots)) !== count($winnerSlots)) {
            $fail('một tay hốt nhiều hơn một lần.');
        }
    }

    private function deleteExistingData(User $user): void
    {
        foreach ([CashEntry::class, HuiRound::class, MySlot::class, HuiGroup::class] as $modelClass) {
            $modelClass::query()->withoutGlobalScope(HuiGroup::OWNER_SCOPE)->where('user_id', $user->id)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{groups: int, rounds: int, entries: int}  $stats
     */
    private function restoreGroup(User $user, array $data, array &$stats): void
    {
        $group = (new HuiGroup)->forceFill(['user_id' => $user->id] + array_intersect_key($data, array_flip([
            'name', 'type', 'frequency', 'face_value', 'total_slots', 'start_date', 'due_offset_days',
            'host_name', 'host_phone', 'commission_amount', 'status', 'broken_round_no', 'note',
        ])));
        $group->save();

        $slotIds = [];

        foreach ($data['slots'] as $slotData) {
            $slot = (new MySlot)->forceFill(['user_id' => $user->id, 'group_id' => $group->id, 'label' => $slotData['label']]);
            $slot->save();
            $slotIds[$slotData['key']] = $slot->id;
        }

        foreach ($data['rounds'] as $roundData) {
            $round = (new HuiRound)->forceFill([
                'user_id' => $user->id,
                'group_id' => $group->id,
                'round_no' => $roundData['round_no'],
                'open_date' => $roundData['open_date'],
                'bid_amount' => $roundData['bid_amount'],
                'winner_type' => $roundData['winner_type'],
                'winner_slot_id' => $roundData['winner_slot'] === null ? null : $slotIds[$roundData['winner_slot']],
                'winner_name' => $roundData['winner_name'],
            ]);
            $round->save();
            $stats['rounds']++;

            foreach ($roundData['entries'] as $entryData) {
                (new CashEntry)->forceFill([
                    'user_id' => $user->id,
                    'round_id' => $round->id,
                    'slot_id' => $slotIds[$entryData['slot']],
                ] + array_intersect_key($entryData, array_flip(['direction', 'amount_expected', 'amount_actual', 'paid_date', 'status', 'note'])))->save();
                $stats['entries']++;
            }
        }

        $stats['groups']++;
    }
}
