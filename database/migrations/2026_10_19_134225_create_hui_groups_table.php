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
        Schema::create('hui_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('co_lai'); // co_lai, khong_lai
            $table->string('frequency')->default('thang'); // thang
            $table->unsignedBigInteger('face_value'); // M
            $table->unsignedInteger('total_slots'); // N
            $table->date('start_date');
            $table->unsignedInteger('due_offset_days')->default(0);
            $table->string('host_name');
            $table->string('host_phone')->nullable();
            $table->unsignedBigInteger('commission_amount')->default(0); // tiền thảo mỗi lần tay mình hốt
            $table->string('status')->default('dang_chay'); // dang_chay, ket_thuc, be
            $table->unsignedInteger('broken_round_no')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE hui_groups
                ADD CONSTRAINT hui_groups_face_value_check CHECK (face_value > 0),
                ADD CONSTRAINT hui_groups_total_slots_check CHECK (total_slots >= 2),
                ADD CONSTRAINT hui_groups_broken_round_no_check CHECK (broken_round_no IS NULL OR broken_round_no BETWEEN 1 AND total_slots)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hui_groups');
    }
};
