<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Models\HostSlot;
use App\Models\HuiRound;
use Illuminate\Support\Facades\DB;

/**
 * Nhập kết quả một kỳ của dây chủ hụi: lãi thăm và tay hốt (CH-04), rồi tính lại
 * các khoản thu / chi (CH-05). Truyền $bidAmount = null và $winner = null để xóa kết quả.
 */
final class RecordHostRoundResult
{
    public function __construct(private SyncHostEntries $syncHostEntries) {}

    /**
     * @throws ConfirmationRequiredException khi kỳ đã có tiền thực tế và $confirmed = false.
     * @throws HuiActionException khi kết quả vi phạm quy tắc.
     */
    public function handle(HuiRound $round, ?int $bidAmount, ?HostSlot $winner, bool $confirmed = false): HuiRound
    {
        $group = $round->group;

        if ($group->status === HuiGroupStatus::Be && $round->round_no >= $group->broken_round_no) {
            throw new HuiActionException("Dây đã bể từ kỳ {$group->broken_round_no}, không nhập kết quả cho kỳ đó trở đi.");
        }

        if ($winner !== null && $bidAmount === null) {
            throw new HuiActionException('Cần nhập lãi thăm trước khi chọn tay hốt.');
        }

        if ($winner !== null) {
            if ($winner->group_id !== $round->group_id) {
                throw new HuiActionException('Tay hốt không thuộc dây này.');
            }

            $otherWonRound = $winner->wonRound()->whereKeyNot($round->getKey())->first();

            if ($otherWonRound !== null) {
                throw new HuiActionException("Tay {$winner->slot_no} đã hốt ở kỳ {$otherWonRound->round_no}.");
            }
        }

        return DB::transaction(function () use ($round, $group, $bidAmount, $winner, $confirmed): HuiRound {
            $round->update([
                'bid_amount' => $bidAmount,
                'winner_type' => $winner === null ? WinnerType::ChuaCo : WinnerType::NguoiKhac,
                'winner_host_slot_id' => $winner?->id,
            ]);

            $conflicts = $this->syncHostEntries->handle($group, overwritePaid: $confirmed);

            if ($conflicts !== []) {
                throw new ConfirmationRequiredException(count($conflicts));
            }

            return $round->refresh();
        });
    }
}
