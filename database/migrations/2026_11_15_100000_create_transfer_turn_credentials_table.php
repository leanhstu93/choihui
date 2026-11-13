<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mật khẩu TURN tạm thời (Open Relay / Metered) tạo sẵn: mật khẩu mới cần tới 2 phút mới dùng
     * được, nên server tạo trước rồi giao cho từng phiên. Mỗi mật khẩu chỉ giao cho một phòng.
     */
    public function up(): void
    {
        Schema::create('transfer_turn_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('username', 191);
            $table->text('ice_servers');
            $table->timestamp('ready_at');
            $table->timestamp('expires_at');
            $table->foreignUlid('transfer_room_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['issued_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_turn_credentials');
    }
};
