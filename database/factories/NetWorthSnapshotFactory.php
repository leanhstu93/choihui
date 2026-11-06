<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NetWorthSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetWorthSnapshot>
 */
class NetWorthSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month' => '2026-10',
            'assets' => 50_000_000,
            'debts' => 0,
        ];
    }
}
