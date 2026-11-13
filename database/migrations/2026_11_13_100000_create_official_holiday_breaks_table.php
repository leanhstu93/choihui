<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Lịch nghỉ lễ chính thức do quản trị nhập khi Chính phủ công bố (thay cho lịch dự kiến tự tính).
     */
    public function up(): void
    {
        Schema::create('official_holiday_breaks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->string('kind', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('make_up_on')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['year', 'kind']);
        });

        // Đã công bố trước khi có màn nhập: Thông báo 11483/TB-BNV ngày 09/10/2026 của Bộ Nội vụ.
        $source = 'Thông báo 11483/TB-BNV ngày 09/10/2026 của Bộ Nội vụ';
        $now = now();
        DB::table('official_holiday_breaks')->insert([
            ['id' => (string) Str::ulid(), 'year' => 2027, 'kind' => 'tet_nguyen_dan', 'starts_on' => '2027-02-04', 'ends_on' => '2027-02-10', 'make_up_on' => null, 'source' => $source, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) Str::ulid(), 'year' => 2027, 'kind' => 'quoc_khanh', 'starts_on' => '2027-09-02', 'ends_on' => '2027-09-05', 'make_up_on' => null, 'source' => $source, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('official_holiday_breaks');
    }
};
