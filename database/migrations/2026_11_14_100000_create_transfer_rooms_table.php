<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gửi file trực tiếp (/gui-file): phòng ghép đôi hai thiết bị và tin nhắn bắt tay WebRTC.
     * Không có cột nào chứa tên file hay nội dung file: những thứ đó chỉ đi giữa hai thiết bị.
     */
    public function up(): void
    {
        Schema::create('transfer_rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('code', 6)->unique();
            $table->char('sender_token_hash', 64);
            $table->char('receiver_token_hash', 64)->nullable();
            $table->timestamp('receiver_joined_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('created_at')->nullable();

            $table->index('last_activity_at');
        });

        Schema::create('transfer_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('transfer_room_id')->constrained()->cascadeOnDelete();
            $table->string('recipient', 10);
            $table->mediumText('payload');
            $table->timestamp('created_at')->nullable();

            $table->index(['transfer_room_id', 'recipient', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_signals');
        Schema::dropIfExists('transfer_rooms');
    }
};
