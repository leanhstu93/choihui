<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('host_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('round_id')->constrained('hui_rounds')->cascadeOnDelete();
            $table->foreignUlid('host_slot_id')->constrained('host_slots')->cascadeOnDelete();
            $table->string('direction'); // thu, chi
            $table->unsignedBigInteger('amount_expected');
            $table->unsignedBigInteger('amount_actual')->default(0);
            $table->date('paid_date')->nullable();
            $table->string('status')->default('chua'); // chua, du, thieu, muon, con_no, bung
            $table->text('note')->nullable();
            $table->unique(['round_id', 'host_slot_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('host_entries');
    }
};
