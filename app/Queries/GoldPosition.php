<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\GoldLedger;
use App\Models\GoldItem;
use App\Models\GoldPrice;

/**
 * Tình trạng một món vàng theo giá hiện tại. value và unrealized là null khi loại
 * vàng này chưa có giá (document/06-vang.md V8). Cần nạp sẵn quan hệ sales.
 */
final readonly class GoldPosition
{
    public int $soldWeight;

    public int $soldAmount;

    public int $remainingWeight;

    public int $remainingCost;

    public int $realized;

    public ?int $value;

    public ?int $unrealized;

    public function __construct(public GoldItem $item, public ?GoldPrice $price)
    {
        $this->soldWeight = (int) $item->sales->sum('weight_ly');
        $this->soldAmount = (int) $item->sales->sum('amount');
        $this->remainingWeight = GoldLedger::remainingWeight($item->weight_ly, $this->soldWeight);
        $this->remainingCost = GoldLedger::remainingCost($item->cost_amount, $item->weight_ly, $this->soldWeight);
        $this->realized = GoldLedger::realizedProfit($item->cost_amount, $item->weight_ly, $this->soldWeight, $this->soldAmount);
        $this->value = $price === null ? null : GoldLedger::value($this->remainingWeight, $price->price_per_chi);
        $this->unrealized = $price === null ? null : $this->value - $this->remainingCost;
    }

    public function isHeld(): bool
    {
        return $this->remainingWeight > 0;
    }
}
