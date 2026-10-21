<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tay hốt của dây chủ hụi (document/04-chu-hui.md mục 6). Với dây chủ hụi,
     * người hốt luôn là hụi viên nên winner_type là nguoi_khac.
     */
    public function up(): void
    {
        Schema::table('hui_rounds', function (Blueprint $table) {
            $table->foreignUlid('winner_host_slot_id')->nullable()->unique()->after('winner_slot_id')->constrained('host_slots');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE hui_rounds ADD CONSTRAINT hui_rounds_winner_host_slot_check CHECK (winner_host_slot_id IS NULL OR winner_type = 'nguoi_khac')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE hui_rounds DROP CHECK hui_rounds_winner_host_slot_check');
        }

        Schema::table('hui_rounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_host_slot_id');
        });
    }
};
