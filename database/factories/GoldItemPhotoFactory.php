<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoldItem;
use App\Models\GoldItemPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldItemPhoto>
 */
class GoldItemPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gold_item_id' => GoldItem::factory(),
            'user_id' => fn (array $attributes) => GoldItem::withoutGlobalScopes()->findOrFail($attributes['gold_item_id'])->user_id,
            'path' => fn (array $attributes) => "gold/{$attributes['user_id']}/".fake()->uuid().'.jpg',
        ];
    }
}
