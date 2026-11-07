<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ sửa chữa (document/08-sua-chua.md mục 5).
     */
    public function up(): void
    {
        Schema::create('repair_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('tracks_km')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('repair_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('repair_item_id')->constrained('repair_items')->cascadeOnDelete();
            $table->date('repaired_on');
            $table->string('part', 100);
            $table->unsignedBigInteger('amount');
            $table->string('place', 100)->nullable();
            $table->date('warranty_until')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'repaired_on']);
        });

        Schema::create('repair_record_photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('repair_record_id')->constrained('repair_records')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_record_photos');
        Schema::dropIfExists('repair_records');
        Schema::dropIfExists('repair_items');
    }
};
