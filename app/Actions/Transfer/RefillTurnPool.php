<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Models\TransferTurnCredential;
use Throwable;

/**
 * Giữ sẵn vài mật khẩu TURN chưa giao trong kho. Mật khẩu Metered mới tạo cần tới 2 phút mới dùng
 * được ở mọi máy chủ, nên phải tạo trước khi có người cần. Chạy mỗi phút qua transfer:turn-pool.
 */
final class RefillTurnPool
{
    public function __construct(
        private readonly MeteredTurnClient $client,
        private readonly TurnQuota $quota,
    ) {}

    /**
     * @return int số mật khẩu vừa tạo
     */
    public function handle(): int
    {
        TransferTurnCredential::query()->expired()->delete();

        if (! MeteredTurnClient::configured() || $this->quota->exhausted(refresh: true)) {
            return 0;
        }

        $missing = (int) config('services.transfer.turn_pool_size') - TransferTurnCredential::query()->available()->count();
        $created = 0;

        for ($i = 0; $i < $missing; $i++) {
            try {
                $this->create();
                $created++;
            } catch (Throwable $exception) {
                report($exception);

                break;
            }
        }

        return $created;
    }

    /**
     * Tạo một mật khẩu mới trong kho (dùng được sau thời gian lan truyền).
     */
    public function create(): TransferTurnCredential
    {
        $lifetime = (int) config('services.transfer.turn_credential_hours') * 3600;
        ['username' => $username, 'iceServers' => $iceServers] = $this->client->createCredential($lifetime, 'gui-file');

        return TransferTurnCredential::query()->create([
            'username' => $username,
            'ice_servers' => $iceServers,
            'ready_at' => now()->addSeconds((int) config('services.transfer.turn_propagation_seconds')),
            'expires_at' => now()->addSeconds($lifetime),
        ]);
    }
}
