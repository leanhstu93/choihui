<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Số tổng tài sản từng tháng (document/07-tong-tai-san.md mục 4).
     */
    public function up(): void
    {
        Schema::create('net_worth_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7);
            $table->unsignedBigInteger('assets');
            $table->unsignedBigInteger('debts');
            $table->timestamps();

            $table->unique(['user_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('net_worth_snapshots');
    }
};
