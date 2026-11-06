<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ vàng (document/06-vang.md mục 5).
     */
    public function up(): void
    {
        Schema::create('gold_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gold_type', 20);
            $table->string('name')->nullable();
            $table->unsignedInteger('weight_ly');
            $table->unsignedBigInteger('cost_amount');
            $table->date('bought_on');
            $table->string('shop')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'bought_on']);
        });

        Schema::create('gold_sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('gold_item_id')->constrained('gold_items')->cascadeOnDelete();
            $table->unsignedInteger('weight_ly');
            $table->unsignedBigInteger('amount');
            $table->date('sold_on');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('gold_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gold_type', 20);
            $table->unsignedBigInteger('price_per_chi');
            $table->date('priced_on');
            $table->timestamps();

            $table->unique(['user_id', 'gold_type']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE gold_items ADD CONSTRAINT gold_items_weight_check CHECK (weight_ly > 0)');
            DB::statement('ALTER TABLE gold_sales ADD CONSTRAINT gold_sales_weight_check CHECK (weight_ly > 0)');
            DB::statement('ALTER TABLE gold_prices ADD CONSTRAINT gold_prices_price_check CHECK (price_per_chi > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
        Schema::dropIfExists('gold_sales');
        Schema::dropIfExists('gold_items');
    }
};
