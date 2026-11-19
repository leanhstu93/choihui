<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ việc cần làm (document/10-viec-can-lam.md mục 5).
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('note')->nullable();
            $table->date('due_on')->nullable();
            $table->time('due_time')->nullable();
            $table->boolean('important')->default(false);
            $table->string('recurrence', 20)->default('none');
            $table->unsignedTinyInteger('repeat_weekdays')->default(0);
            $table->unsignedTinyInteger('repeat_day')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignUlid('spawned_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'completed_at', 'due_on']);
        });

        Schema::create('task_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 200);
            $table->boolean('done')->default(false);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('tasks_in_morning_email')->default(true)->after('family_reminded_on');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tasks
                ADD CONSTRAINT tasks_time_needs_date_check CHECK (due_time IS NULL OR due_on IS NOT NULL),
                ADD CONSTRAINT tasks_repeat_needs_date_check CHECK (recurrence = 'none' OR due_on IS NOT NULL),
                ADD CONSTRAINT tasks_weekdays_check CHECK (repeat_weekdays <= 127),
                ADD CONSTRAINT tasks_repeat_day_check CHECK (repeat_day IS NULL OR repeat_day BETWEEN 1 AND 31)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tasks_in_morning_email');
        });

        Schema::dropIfExists('task_steps');
        Schema::dropIfExists('tasks');
    }
};
