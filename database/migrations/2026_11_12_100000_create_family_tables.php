<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ gia đình: ngày nhớ và sức khỏe (document/09-gia-dinh.md mục 5).
     */
    public function up(): void
    {
        Schema::create('family_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('kind', 20);
            $table->string('calendar', 10);
            $table->unsignedTinyInteger('day');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('origin_year')->nullable();
            $table->unsignedTinyInteger('remind_days_before')->default(3);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('health_people', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('health_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('health_person_id')->constrained('health_people')->cascadeOnDelete();
            $table->date('visited_on');
            $table->string('kind', 20);
            $table->string('title', 150);
            $table->string('place', 100)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'visited_on']);
            $table->index(['user_id', 'follow_up_on']);
        });

        Schema::create('health_record_photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('health_record_id')->constrained('health_records')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('family_reminded_on')->nullable()->after('last_reminded_on');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE family_events
                ADD CONSTRAINT family_events_month_check CHECK (month BETWEEN 1 AND 12),
                ADD CONSTRAINT family_events_day_check CHECK (day >= 1 AND day <= IF(calendar = 'am', 30, 31)),
                ADD CONSTRAINT family_events_remind_check CHECK (remind_days_before <= 30)");
            DB::statement('ALTER TABLE health_records ADD CONSTRAINT health_records_follow_up_check CHECK (follow_up_on IS NULL OR follow_up_on >= visited_on)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('family_reminded_on');
        });

        Schema::dropIfExists('health_record_photos');
        Schema::dropIfExists('health_records');
        Schema::dropIfExists('health_people');
        Schema::dropIfExists('family_events');
    }
};
