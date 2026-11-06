<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\GoldType;
use App\Enums\HuiFrequency;
use App\Enums\HuiGroupStatus;
use App\Enums\HuiGroupType;
use App\Enums\WinnerType;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Models\GoldItem;
use App\Models\GoldPrice;
use App\Models\GoldSale;
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
 * Sổ vàng chỉ bị thay khi file có khóa gold; file cũ không có khóa này thì giữ nguyên
 * (document/06-vang.md V11).
 *
 * Kiểm tra cả file trước khi xóa gì; lỗi thì dữ liệu cũ giữ nguyên. user_id luôn là người
 * dùng được truyền vào, không bao giờ lấy từ file.
 */
final class RestoreBackup
{
    /**
     * @param  array<mixed>  $data
     * @return array{groups: int, rounds: int, entries: int, gold_items: int}
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

        $gold = $data['gold'] ?? null;

        if ($gold !== null) {
            $this->validateGold($gold);
        }

        return DB::transaction(function () use ($user, $data, $gold): array {
            $this->deleteExistingData($user);

            $stats = ['groups' => 0, 'rounds' => 0, 'entries' => 0, 'gold_items' => 0];

            foreach ($data['groups'] as $groupData) {
                $this->restoreGroup($user, $groupData, $stats);
            }

            if ($gold !== null) {
                $this->deleteExistingGold($user);
                $this->restoreGold($user, $gold, $stats);
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
            'gold' => ['sometimes', 'array'],
            'gold.items' => ['present_with:gold', 'array', 'max:5000'],
            'gold.items.*.gold_type' => ['required', Rule::enum(GoldType::class)],
            'gold.items.*.name' => ['nullable', 'string', 'max:100'],
            'gold.items.*.weight_ly' => ['required', 'integer', 'min:1', 'max:1000000'],
            'gold.items.*.cost_amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'gold.items.*.bought_on' => ['required', 'date_format:Y-m-d'],
            'gold.items.*.shop' => ['nullable', 'string', 'max:100'],
            'gold.items.*.note' => ['nullable', 'string', 'max:2000'],
            'gold.items.*.sales' => ['present', 'array'],
            'gold.items.*.sales.*.weight_ly' => ['required', 'integer', 'min:1', 'max:1000000'],
            'gold.items.*.sales.*.amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'gold.items.*.sales.*.sold_on' => ['required', 'date_format:Y-m-d'],
            'gold.items.*.sales.*.note' => ['nullable', 'string', 'max:2000'],
            'gold.prices' => ['present_with:gold', 'array'],
            'gold.prices.*.gold_type' => ['required', 'distinct', Rule::enum(GoldType::class)],
            'gold.prices.*.price_per_chi' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'gold.prices.*.priced_on' => ['required', 'date_format:Y-m-d'],
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

    /**
     * Ràng buộc nghiệp vụ giống SellGold.
     *
     * @param  array<string, mixed>  $gold
     */
    private function validateGold(array $gold): void
    {
        foreach ($gold['items'] as $index => $item) {
            $position = $index + 1;

            if (array_sum(array_column($item['sales'], 'weight_ly')) > $item['weight_ly']) {
                throw new HuiActionException("Món vàng thứ {$position}: số chỉ đã bán vượt quá khối lượng món.");
            }

            foreach ($item['sales'] as $sale) {
                if ($sale['sold_on'] < $item['bought_on']) {
                    throw new HuiActionException("Món vàng thứ {$position}: ngày bán trước ngày mua.");
                }
            }
        }
    }

    private function deleteExistingData(User $user): void
    {
        foreach ([CashEntry::class, HuiRound::class, MySlot::class, HuiGroup::class] as $modelClass) {
            $modelClass::query()->withoutGlobalScope(HuiGroup::OWNER_SCOPE)->where('user_id', $user->id)->delete();
        }
    }

    private function deleteExistingGold(User $user): void
    {
        foreach ([GoldSale::class, GoldItem::class, GoldPrice::class] as $modelClass) {
            $modelClass::query()->withoutGlobalScope(GoldItem::OWNER_SCOPE)->where('user_id', $user->id)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $gold
     * @param  array{groups: int, rounds: int, entries: int, gold_items: int}  $stats
     */
    private function restoreGold(User $user, array $gold, array &$stats): void
    {
        foreach ($gold['items'] as $itemData) {
            $item = (new GoldItem)->forceFill(['user_id' => $user->id] + array_intersect_key($itemData, array_flip([
                'gold_type', 'name', 'weight_ly', 'cost_amount', 'bought_on', 'shop', 'note',
            ])));
            $item->save();
            $stats['gold_items']++;

            foreach ($itemData['sales'] as $saleData) {
                (new GoldSale)->forceFill(['user_id' => $user->id, 'gold_item_id' => $item->id] + array_intersect_key(
                    $saleData,
                    array_flip(['weight_ly', 'amount', 'sold_on', 'note']),
                ))->save();
            }
        }

        foreach ($gold['prices'] as $priceData) {
            (new GoldPrice)->forceFill(['user_id' => $user->id] + array_intersect_key(
                $priceData,
                array_flip(['gold_type', 'price_per_chi', 'priced_on']),
            ))->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{groups: int, rounds: int, entries: int, gold_items: int}  $stats
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
