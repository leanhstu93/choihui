<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Domain\FamilyCalendar;
use App\Enums\CalendarType;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\FamilyEventKind;
use App\Enums\GoldType;
use App\Enums\HealthRecordKind;
use App\Enums\HuiFrequency;
use App\Enums\HuiGroupStatus;
use App\Enums\HuiGroupType;
use App\Enums\WinnerType;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Models\FamilyEvent;
use App\Models\GoldItem;
use App\Models\GoldItemPhoto;
use App\Models\GoldPrice;
use App\Models\GoldSale;
use App\Models\HealthPerson;
use App\Models\HealthRecord;
use App\Models\HealthRecordPhoto;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\NetWorthSnapshot;
use App\Models\RepairItem;
use App\Models\RepairRecord;
use App\Models\RepairRecordPhoto;
use App\Models\User;
use App\Queries\UserBackup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Khôi phục dữ liệu từ file sao lưu (FR-11): THAY THẾ toàn bộ dây hụi hiện có của người dùng.
 *
 * Sổ vàng chỉ bị thay khi file có khóa gold; file cũ không có khóa này thì giữ nguyên
 * (document/06-vang.md V11). Lịch sử tổng tài sản cũng vậy với khóa net_worth
 * (document/07-tong-tai-san.md T8) và sổ sửa chữa với khóa repairs (document/08-sua-chua.md S7), sổ gia đình với khóa family
 * (document/09-gia-dinh.md G12).
 *
 * Kiểm tra cả file trước khi xóa gì; lỗi thì dữ liệu cũ giữ nguyên. user_id luôn là người
 * dùng được truyền vào, không bao giờ lấy từ file.
 */
final class RestoreBackup
{
    /**
     * @param  array<mixed>  $data
     * @return array{groups: int, rounds: int, entries: int, gold_items: int, repair_items: int, family_events: int, health_people: int}
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

        $repairs = $data['repairs'] ?? null;

        if ($repairs !== null) {
            $this->validateRepairs($repairs);
        }

        $family = $data['family'] ?? null;

        if ($family !== null) {
            $this->validateFamily($family);
        }

        $orphanedPhotos = [];

        $stats = DB::transaction(function () use ($user, $data, $gold, $repairs, $family, &$orphanedPhotos): array {
            $this->deleteExistingData($user);

            $stats = ['groups' => 0, 'rounds' => 0, 'entries' => 0, 'gold_items' => 0, 'repair_items' => 0, 'family_events' => 0, 'health_people' => 0];

            foreach ($data['groups'] as $groupData) {
                $this->restoreGroup($user, $groupData, $stats);
            }

            if ($gold !== null) {
                $orphanedPhotos = $this->deleteExistingGold($user);
                $this->restoreGold($user, $gold, $stats);
            }

            if ($repairs !== null) {
                $orphanedPhotos = [...$orphanedPhotos, ...$this->deleteExistingRepairs($user)];
                $this->restoreRepairs($user, $repairs, $stats);
            }

            if ($family !== null) {
                $orphanedPhotos = [...$orphanedPhotos, ...$this->deleteExistingFamily($user)];
                $this->restoreFamily($user, $family, $stats);
            }

            if (isset($data['net_worth'])) {
                $this->restoreNetWorth($user, $data['net_worth']);
            }

            if (isset($data['settings']['reminder_days_before'])) {
                $user->forceFill(['reminder_days_before' => $data['settings']['reminder_days_before']])->save();
            }

            return $stats;
        });

        Storage::disk(GoldItemPhoto::DISK)->delete($orphanedPhotos);

        return $stats;
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
            'net_worth' => ['sometimes', 'array', 'max:1200'],
            'net_worth.*.month' => ['required', 'distinct', 'date_format:Y-m'],
            'net_worth.*.assets' => ['required', 'integer', 'min:0'],
            'net_worth.*.debts' => ['required', 'integer', 'min:0'],
            'repairs' => ['sometimes', 'array'],
            'repairs.items' => ['present_with:repairs', 'array', 'max:5000'],
            'repairs.items.*.name' => ['required', 'string', 'max:100'],
            'repairs.items.*.tracks_km' => ['required', 'boolean'],
            'repairs.items.*.note' => ['nullable', 'string', 'max:2000'],
            'repairs.items.*.records' => ['present', 'array'],
            'repairs.items.*.records.*.repaired_on' => ['required', 'date_format:Y-m-d'],
            'repairs.items.*.records.*.part' => ['required', 'string', 'max:100'],
            'repairs.items.*.records.*.amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'repairs.items.*.records.*.place' => ['nullable', 'string', 'max:100'],
            'repairs.items.*.records.*.warranty_until' => ['nullable', 'date_format:Y-m-d'],
            'repairs.items.*.records.*.odometer_km' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'repairs.items.*.records.*.note' => ['nullable', 'string', 'max:2000'],
            'family' => ['sometimes', 'array'],
            'family.events' => ['present_with:family', 'array', 'max:5000'],
            'family.events.*.title' => ['required', 'string', 'max:100'],
            'family.events.*.kind' => ['required', Rule::enum(FamilyEventKind::class)],
            'family.events.*.calendar' => ['required', Rule::enum(CalendarType::class)],
            'family.events.*.day' => ['required', 'integer', 'min:1', 'max:31'],
            'family.events.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'family.events.*.origin_year' => ['nullable', 'integer', 'min:1800', 'max:2200'],
            'family.events.*.remind_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'family.events.*.note' => ['nullable', 'string', 'max:2000'],
            'family.people' => ['present_with:family', 'array', 'max:1000'],
            'family.people.*.name' => ['required', 'string', 'max:100'],
            'family.people.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'family.people.*.note' => ['nullable', 'string', 'max:2000'],
            'family.people.*.records' => ['present', 'array'],
            'family.people.*.records.*.visited_on' => ['required', 'date_format:Y-m-d'],
            'family.people.*.records.*.kind' => ['required', Rule::enum(HealthRecordKind::class)],
            'family.people.*.records.*.title' => ['required', 'string', 'max:150'],
            'family.people.*.records.*.place' => ['nullable', 'string', 'max:100'],
            'family.people.*.records.*.amount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'family.people.*.records.*.follow_up_on' => ['nullable', 'date_format:Y-m-d'],
            'family.people.*.records.*.note' => ['nullable', 'string', 'max:2000'],
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

    /**
     * Ràng buộc nghiệp vụ giống StoreRepairRecordRequest.
     *
     * @param  array<string, mixed>  $repairs
     */
    private function validateRepairs(array $repairs): void
    {
        foreach ($repairs['items'] as $index => $item) {
            foreach ($item['records'] as $record) {
                if ($record['warranty_until'] !== null && $record['warranty_until'] < $record['repaired_on']) {
                    throw new HuiActionException('Món đồ thứ '.($index + 1).': ngày bảo hành trước ngày sửa.');
                }
            }
        }
    }

    /**
     * Ràng buộc nghiệp vụ giống StoreFamilyEventRequest, StoreHealthRecordRequest và CHECK trong database.
     *
     * @param  array<string, mixed>  $family
     */
    private function validateFamily(array $family): void
    {
        foreach ($family['events'] as $index => $event) {
            if (! FamilyCalendar::isValid($event['calendar'] === CalendarType::Am->value, $event['day'], $event['month'])) {
                throw new HuiActionException('Ngày nhớ thứ '.($index + 1).": ngày {$event['day']}/{$event['month']} không có thật.");
            }
        }

        foreach ($family['people'] as $index => $person) {
            foreach ($person['records'] as $record) {
                if (($record['follow_up_on'] ?? null) !== null && $record['follow_up_on'] < $record['visited_on']) {
                    throw new HuiActionException('Người thứ '.($index + 1).': ngày tái khám trước ngày khám.');
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

    /**
     * Ảnh không nằm trong file sao lưu nên mất theo món vàng cũ (document/06-vang.md V13).
     *
     * @return array<int, string> Đường dẫn file ảnh cần xóa sau khi transaction xong.
     */
    private function deleteExistingGold(User $user): array
    {
        $photoPaths = GoldItemPhoto::query()
            ->withoutGlobalScope(GoldItemPhoto::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->pluck('path')
            ->all();

        foreach ([GoldSale::class, GoldItem::class, GoldPrice::class] as $modelClass) {
            $modelClass::query()->withoutGlobalScope(GoldItem::OWNER_SCOPE)->where('user_id', $user->id)->delete();
        }

        return $photoPaths;
    }

    /**
     * Ảnh không nằm trong file sao lưu nên mất theo lần sửa cũ (document/08-sua-chua.md S7).
     *
     * @return array<int, string> Đường dẫn file ảnh cần xóa sau khi transaction xong.
     */
    private function deleteExistingRepairs(User $user): array
    {
        $photoPaths = RepairRecordPhoto::query()
            ->withoutGlobalScope(RepairRecordPhoto::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->pluck('path')
            ->all();

        RepairItem::query()->withoutGlobalScope(RepairItem::OWNER_SCOPE)->where('user_id', $user->id)->delete();

        return $photoPaths;
    }

    /**
     * @param  array<string, mixed>  $repairs
     * @param  array{groups: int, rounds: int, entries: int, gold_items: int, repair_items: int, family_events: int, health_people: int}  $stats
     */
    private function restoreRepairs(User $user, array $repairs, array &$stats): void
    {
        foreach ($repairs['items'] as $itemData) {
            $item = (new RepairItem)->forceFill(['user_id' => $user->id] + array_intersect_key($itemData, array_flip(['name', 'tracks_km', 'note'])));
            $item->save();
            $stats['repair_items']++;

            foreach ($itemData['records'] as $recordData) {
                (new RepairRecord)->forceFill(['user_id' => $user->id, 'repair_item_id' => $item->id] + array_intersect_key(
                    $recordData,
                    array_flip(['repaired_on', 'part', 'amount', 'place', 'warranty_until', 'odometer_km', 'note']),
                ))->save();
            }
        }
    }

    /**
     * Ảnh không nằm trong file sao lưu nên mất theo lần khám cũ (document/09-gia-dinh.md G12).
     *
     * @return array<int, string> Đường dẫn file ảnh cần xóa sau khi transaction xong.
     */
    private function deleteExistingFamily(User $user): array
    {
        $photoPaths = HealthRecordPhoto::query()
            ->withoutGlobalScope(HealthRecordPhoto::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->pluck('path')
            ->all();

        HealthPerson::query()->withoutGlobalScope(HealthPerson::OWNER_SCOPE)->where('user_id', $user->id)->delete();
        FamilyEvent::query()->withoutGlobalScope(FamilyEvent::OWNER_SCOPE)->where('user_id', $user->id)->delete();

        return $photoPaths;
    }

    /**
     * @param  array<string, mixed>  $family
     * @param  array{groups: int, rounds: int, entries: int, gold_items: int, repair_items: int, family_events: int, health_people: int}  $stats
     */
    private function restoreFamily(User $user, array $family, array &$stats): void
    {
        foreach ($family['events'] as $eventData) {
            (new FamilyEvent)->forceFill(['user_id' => $user->id] + array_intersect_key($eventData, array_flip([
                'title', 'kind', 'calendar', 'day', 'month', 'origin_year', 'remind_days_before', 'note',
            ])))->save();
            $stats['family_events']++;
        }

        foreach ($family['people'] as $personData) {
            $person = (new HealthPerson)->forceFill(['user_id' => $user->id] + array_intersect_key($personData, array_flip(['name', 'birth_year', 'note'])));
            $person->save();
            $stats['health_people']++;

            foreach ($personData['records'] as $recordData) {
                (new HealthRecord)->forceFill(['user_id' => $user->id, 'health_person_id' => $person->id] + array_intersect_key(
                    $recordData,
                    array_flip(['visited_on', 'kind', 'title', 'place', 'amount', 'follow_up_on', 'note']),
                ))->save();
            }
        }
    }

    /**
     * @param  list<array{month: string, assets: int, debts: int}>  $snapshots
     */
    private function restoreNetWorth(User $user, array $snapshots): void
    {
        NetWorthSnapshot::query()->withoutGlobalScope(NetWorthSnapshot::OWNER_SCOPE)->where('user_id', $user->id)->delete();

        foreach ($snapshots as $snapshot) {
            (new NetWorthSnapshot)->forceFill(['user_id' => $user->id] + array_intersect_key(
                $snapshot,
                array_flip(['month', 'assets', 'debts']),
            ))->save();
        }
    }

    /**
     * @param  array<string, mixed>  $gold
     * @param  array{groups: int, rounds: int, entries: int, gold_items: int, repair_items: int, family_events: int, health_people: int}  $stats
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
     * @param  array{groups: int, rounds: int, entries: int, gold_items: int, repair_items: int, family_events: int, health_people: int}  $stats
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
