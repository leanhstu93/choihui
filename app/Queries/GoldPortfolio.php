<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\GoldType;
use App\Models\GoldItem;
use App\Models\GoldPrice;
use App\Models\GoldSale;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tổng hợp sổ vàng của một người dùng (document/06-vang.md VG-07). Lọc user_id tường
 * minh, không phụ thuộc người đăng nhập.
 */
final class GoldPortfolio
{
    /**
     * @return array{
     *     held: Collection<int, GoldPosition>,
     *     soldOut: Collection<int, GoldPosition>,
     *     types: list<array{type: GoldType, weight: int, price: GoldPrice|null}>,
     *     totals: array{weight: int, cost: int, value: int, unrealized: int, realized: int, unpriced: int},
     * }
     */
    public function forUser(User $user): array
    {
        $prices = $this->prices($user);
        $positions = GoldItem::query()
            ->withoutGlobalScope(GoldItem::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with(['sales' => fn ($query) => $query->withoutGlobalScope(GoldSale::OWNER_SCOPE)])
            ->orderByDesc('bought_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (GoldItem $item) => new GoldPosition($item, $prices->get($item->gold_type->value)));

        [$held, $soldOut] = $positions->partition(fn (GoldPosition $position) => $position->isHeld());

        return [
            'held' => $held->values(),
            'soldOut' => $soldOut->values(),
            'types' => $this->types($held, $prices),
            'totals' => [
                'weight' => (int) $held->sum('remainingWeight'),
                'cost' => (int) $held->sum('remainingCost'),
                'value' => (int) $held->sum('value'),
                'unrealized' => (int) $held->sum('unrealized'),
                'realized' => (int) $positions->sum('realized'),
                'unpriced' => $held->whereNull('price')->count(),
            ],
        ];
    }

    /**
     * Tình trạng một món đã nạp, dùng cho trang chi tiết.
     */
    public function position(User $user, GoldItem $item): GoldPosition
    {
        return new GoldPosition($item, $this->prices($user)->get($item->gold_type->value));
    }

    /**
     * @return Collection<string, GoldPrice>
     */
    private function prices(User $user): Collection
    {
        return GoldPrice::query()
            ->withoutGlobalScope(GoldPrice::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (GoldPrice $price) => $price->gold_type->value)
            ->toBase();
    }

    /**
     * Mọi loại vàng theo thứ tự trong enum, để nhập được giá cả loại chưa mua.
     *
     * @param  Collection<int, GoldPosition>  $held
     * @param  Collection<string, GoldPrice>  $prices
     * @return list<array{type: GoldType, weight: int, price: GoldPrice|null}>
     */
    private function types(Collection $held, Collection $prices): array
    {
        $rows = [];

        foreach (GoldType::cases() as $type) {
            $rows[] = [
                'type' => $type,
                'weight' => (int) $held
                    ->filter(fn (GoldPosition $position) => $position->item->gold_type === $type)
                    ->sum('remainingWeight'),
                'price' => $prices->get($type->value),
            ];
        }

        return $rows;
    }
}
