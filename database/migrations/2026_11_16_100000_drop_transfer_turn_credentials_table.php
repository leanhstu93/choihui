<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TURN chuyển sang credential cố định của Metered trong .env: bỏ kho mật khẩu tạm thời.
     */
    public function up(): void
    {
        Schema::dropIfExists('transfer_turn_credentials');
    }

    public function down(): void
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
};
