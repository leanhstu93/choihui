<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hui_rounds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('hui_groups')->cascadeOnDelete();
            $table->unsignedInteger('round_no');
            $table->date('open_date');
            $table->unsignedBigInteger('bid_amount')->nullable(); // L
            $table->string('winner_type')->default('chua_co'); // chua_co, minh, nguoi_khac
            $table->foreignUlid('winner_slot_id')->nullable()->unique()->constrained('my_slots');
            $table->string('winner_name')->nullable();
            $table->unique(['group_id', 'round_no']);
            $table->index(['user_id', 'open_date']);
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE hui_rounds
                ADD CONSTRAINT hui_rounds_round_no_check CHECK (round_no >= 1),
                ADD CONSTRAINT hui_rounds_winner_slot_check CHECK ((winner_type = 'minh') = (winner_slot_id IS NOT NULL))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hui_rounds');
    }
};
