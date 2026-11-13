<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Enums\TransferRole;
use App\Models\TransferRoom;
use App\Models\TransferSignal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lấy tin nhắn mới gửi cho máy này. Tin có id ≤ $after là máy đã nhận rồi: xóa luôn, server không
 * giữ lại gì sau khi chuyển xong. Mỗi lần lấy cũng giữ phòng còn hạn.
 */
final class PullTransferSignals
{
    public const BATCH = 100;

    /**
     * @return Collection<int, TransferSignal>
     */
    public function handle(TransferRoom $room, TransferRole $role, int $after): Collection
    {
        return DB::transaction(function () use ($room, $role, $after): Collection {
            $room->signals()->where('recipient', $role)->where('id', '<=', $after)->delete();
            $room->forceFill(['last_activity_at' => now()])->save();

            return $room->signals()->where('recipient', $role)->where('id', '>', $after)->orderBy('id')->limit(self::BATCH)->get();
        });
    }
}
