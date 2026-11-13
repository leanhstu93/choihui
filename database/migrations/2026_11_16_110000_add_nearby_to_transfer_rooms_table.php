<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tự thấy máy cùng mạng: phòng đang chờ ghi mạng (IP công cộng đã băm, không lưu IP gốc),
     * tên ngẫu nhiên của máy và lời mời kết nối giữa hai máy cùng mạng.
     */
    public function up(): void
    {
        Schema::table('transfer_rooms', function (Blueprint $table) {
            $table->char('network_hash', 64)->nullable()->after('receiver_joined_at');
            $table->char('nearby_id', 16)->nullable()->unique()->after('network_hash');
            $table->string('nearby_name', 40)->nullable()->after('nearby_id');
            $table->string('nearby_device', 40)->nullable()->after('nearby_name');
            $table->timestamp('nearby_seen_at')->nullable()->after('nearby_device');
            $table->ulid('invite_from_room_id')->nullable()->after('nearby_seen_at');
            $table->timestamp('invite_at')->nullable()->after('invite_from_room_id');
            $table->string('invite_declined_by', 40)->nullable()->after('invite_at');
            $table->timestamp('invite_declined_at')->nullable()->after('invite_declined_by');

            $table->index(['network_hash', 'nearby_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transfer_rooms', function (Blueprint $table) {
            $table->dropIndex(['network_hash', 'nearby_seen_at']);
            $table->dropUnique(['nearby_id']);
            $table->dropColumn(['network_hash', 'nearby_id', 'nearby_name', 'nearby_device', 'nearby_seen_at', 'invite_from_room_id', 'invite_at', 'invite_declined_by', 'invite_declined_at']);
        });
    }
};
