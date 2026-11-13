<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Domain\RoomCode;
use App\Models\TransferRoom;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tạo phòng mới cho máy gửi: mã 6 ký tự không trùng phòng đang mở, kèm token của máy gửi.
 */
final class CreateTransferRoom
{
    private const ATTEMPTS = 10;

    /**
     * @return array{room: TransferRoom, token: string}
     */
    public function handle(): array
    {
        $token = Str::random(48);

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $code = RoomCode::generate();

            // Mã của phòng đã hết hạn được dùng lại: xóa phòng cũ trước.
            TransferRoom::query()->where('code', $code)->expired()->delete();

            if (TransferRoom::query()->where('code', $code)->exists()) {
                continue;
            }

            $room = TransferRoom::query()->create([
                'code' => $code,
                'sender_token_hash' => TransferRoom::hashToken($token),
                'last_activity_at' => now(),
            ]);

            return ['room' => $room, 'token' => $token];
        }

        throw new RuntimeException('Không tạo được mã phòng không trùng.');
    }
}
