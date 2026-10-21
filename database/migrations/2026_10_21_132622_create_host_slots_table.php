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
        Schema::create('host_slots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('hui_groups')->cascadeOnDelete();
            $table->unsignedInteger('slot_no');
            $table->foreignUlid('member_id')->constrained('members')->restrictOnDelete();
            $table->unique(['group_id', 'slot_no']);
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE host_slots ADD CONSTRAINT host_slots_slot_no_check CHECK (slot_no >= 1)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('host_slots');
    }
};
