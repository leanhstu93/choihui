<?php

declare(strict_types=1);

namespace App\Actions\Rounds;

use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Models\HuiRound;
use App\Models\MySlot;
use Illuminate\Support\Facades\DB;

/**
 * Nhập kết quả một kỳ: lãi thăm và người hốt (FR-04), rồi tính lại dòng tiền (FR-05, FR-07).
 * Truyền $bidAmount = null và WinnerType::ChuaCo để xóa kết quả kỳ.
 */
final class RecordRoundResult
{
    public function __construct(private SyncCashEntries $syncCashEntries) {}

    /**
     * @throws ConfirmationRequiredException khi kỳ đã có tiền thực tế và $confirmed = false.
     * @throws HuiActionException khi kết quả vi phạm quy tắc (dây bể, tay không thuộc dây, tay đã hốt...).
     */
    public function handle(
        HuiRound $round,
        ?int $bidAmount,
        WinnerType $winnerType,
        ?MySlot $winnerSlot = null,
        ?string $winnerName = null,
        bool $confirmed = false,
    ): HuiRound {
        $group = $round->group;

        if ($group->status === HuiGroupStatus::Be && $round->round_no > $group->broken_round_no) {
            throw new HuiActionException("Dây đã bể từ kỳ {$group->broken_round_no}, không nhập kết quả cho kỳ sau đó.");
        }

        if ($winnerType !== WinnerType::ChuaCo && $bidAmount === null) {
            throw new HuiActionException('Cần nhập lãi thăm trước khi chọn người hốt.');
        }

        if ($winnerType === WinnerType::Minh) {
            if ($winnerSlot === null || $winnerSlot->group_id !== $round->group_id) {
                throw new HuiActionException('Tay hốt không thuộc dây này.');
            }

            $otherWonRound = $winnerSlot->wonRound()->whereKeyNot($round->getKey())->first();

            if ($otherWonRound !== null) {
                throw new HuiActionException("{$winnerSlot->label} đã hốt ở kỳ {$otherWonRound->round_no}.");
            }
        }

        return DB::transaction(function () use ($round, $group, $bidAmount, $winnerType, $winnerSlot, $winnerName, $confirmed): HuiRound {
            $round->update([
                'bid_amount' => $bidAmount,
                'winner_type' => $winnerType,
                'winner_slot_id' => $winnerType === WinnerType::Minh ? $winnerSlot->id : null,
                'winner_name' => $winnerType === WinnerType::NguoiKhac ? $winnerName : null,
            ]);

            $conflicts = $this->syncCashEntries->handle($group, overwritePaid: $confirmed);

            if ($conflicts !== []) {
                throw new ConfirmationRequiredException(count($conflicts));
            }

            return $round->refresh();
        });
    }
}
