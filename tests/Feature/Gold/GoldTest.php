<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Actions\Gold\DeleteGoldItem;
use App\Actions\Gold\SaveGoldItem;
use App\Actions\Gold\SellGold;
use App\Enums\GoldType;
use App\Exceptions\HuiActionException;
use App\Livewire\Gold\GoldIndex;
use App\Livewire\Gold\GoldItemForm;
use App\Livewire\Gold\GoldItemShow;
use App\Models\GoldItem;
use App\Models\GoldPrice;
use App\Models\GoldSale;
use App\Models\User;
use App\Queries\GoldPortfolio;
use App\Queries\UserBackup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
 * Sổ vàng (document/06-vang.md).
 */

test('both roles can open the gold ledger and see it in the navigation', function (User $user) {
    $this->actingAs($user);

    $this->get(route('gold.index'))->assertOk()
        ->assertSeeInOrder(['Vàng', 'Cài đặt'])
        ->assertSee('Chưa có món vàng nào');
})->with([
    'member' => fn () => User::factory()->create(),
    'host' => fn () => User::factory()->host()->create(),
]);

test('guests are sent to the login page', function () {
    $this->get(route('gold.index'))->assertRedirect(route('login'));
});

test('gold data is isolated between accounts', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $item = GoldItem::factory()->for($bob)->create();
    GoldSale::factory()->create(['gold_item_id' => $item->id]);
    GoldPrice::factory()->for($bob)->create();

    $this->actingAs($alice);

    expect(GoldItem::count())->toBe(0)
        ->and(GoldSale::count())->toBe(0)
        ->and(GoldPrice::count())->toBe(0)
        ->and(app(GoldPortfolio::class)->forUser($alice)['held'])->toBeEmpty();

    $this->get(route('gold.show', $item->id))->assertNotFound();
    $this->get(route('gold.edit', $item->id))->assertNotFound();
});

test('the policy refuses another accounts rows even when the scope is bypassed', function () {
    $alice = User::factory()->create();
    $item = GoldItem::factory()->create();
    $sale = GoldSale::factory()->create(['gold_item_id' => $item->id]);

    expect(Gate::forUser($alice)->allows('view', $item))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('update', $item))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('delete', $item))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('delete', $sale))->toBeFalse();
});

test('the database rejects zero weights and prices and a second price for the same type', function () {
    $user = User::factory()->create();

    expect(fn () => GoldItem::factory()->for($user)->create(['weight_ly' => 0]))->toThrow(QueryException::class);
    expect(fn () => GoldPrice::factory()->for($user)->create(['price_per_chi' => 0]))->toThrow(QueryException::class);

    GoldPrice::factory()->for($user)->create();
    expect(fn () => GoldPrice::factory()->for($user)->create())->toThrow(QueryException::class);
});

test('deleting an account removes its gold data', function () {
    $user = User::factory()->create();
    $item = GoldItem::factory()->for($user)->create();
    GoldSale::factory()->create(['gold_item_id' => $item->id]);
    GoldPrice::factory()->for($user)->create();

    $user->delete();

    expect(GoldItem::withoutGlobalScopes()->count())->toBe(0)
        ->and(GoldSale::withoutGlobalScopes()->count())->toBe(0)
        ->and(GoldPrice::withoutGlobalScopes()->count())->toBe(0);
});

test('adding an item through the form stores chi as ly and opens the item', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(GoldItemForm::class)
        ->set('goldType', 'sjc')
        ->set('name', 'Của hồi môn')
        ->set('weightChi', '1,25')
        ->set('costAmount', 0)
        ->set('boughtOn', '2026-03-01')
        ->call('save')
        ->assertHasNoErrors();

    $item = GoldItem::sole();

    expect($item->user_id)->toBe($user->id)
        ->and($item->gold_type)->toBe(GoldType::Sjc)
        ->and($item->weight_ly)->toBe(125)
        ->and($item->cost_amount)->toBe(0);
});

test('the form rejects bad input', function (string $weight, ?int $cost, string $date, string $errorField) {
    $this->actingAs(User::factory()->create());

    Livewire::test(GoldItemForm::class)
        ->set('weightChi', $weight)
        ->set('costAmount', $cost)
        ->set('boughtOn', $date)
        ->call('save')
        ->assertHasErrors($errorField);

    expect(GoldItem::count())->toBe(0);
})->with([
    'weight is not a number' => ['abc', 1000, '2026-03-01', 'weight_ly'],
    'weight is zero' => ['0', 1000, '2026-03-01', 'weight_ly'],
    'no cost' => ['1', null, '2026-03-01', 'cost_amount'],
    'bought in the future' => ['1', 1000, '2099-01-01', 'bought_on'],
]);

test('the edit form loads the item and saves changes', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create(['weight_ly' => 125]);

    Livewire::test(GoldItemForm::class, ['goldItem' => $item])
        ->assertSet('weightChi', '1,25')
        ->set('weightChi', '2')
        ->call('save')
        ->assertRedirect(route('gold.show', $item));

    expect($item->refresh()->weight_ly)->toBe(200);
});

test('an item cannot shrink below what was sold or be dated after its first sale', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create(['weight_ly' => 200, 'bought_on' => '2026-01-15']);
    GoldSale::factory()->create(['gold_item_id' => $item->id, 'weight_ly' => 100, 'sold_on' => '2026-06-01']);
    $attributes = ['gold_type' => 'sjc', 'weight_ly' => 200, 'cost_amount' => 1, 'bought_on' => '2026-01-15'];

    expect(fn () => app(SaveGoldItem::class)->handle($item, ['weight_ly' => 99] + $attributes))
        ->toThrow(HuiActionException::class, 'nhỏ hơn số đã bán')
        ->and(fn () => app(SaveGoldItem::class)->handle($item, ['bought_on' => '2026-06-02'] + $attributes))
        ->toThrow(HuiActionException::class, 'lần bán đầu tiên');

    app(SaveGoldItem::class)->handle($item, ['weight_ly' => 100, 'bought_on' => '2026-06-01'] + $attributes);

    expect($item->refresh()->weight_ly)->toBe(100);
});

test('a partial sale leaves the rest held and fixes the profit', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create(['weight_ly' => 200, 'cost_amount' => 30_000_000]);
    GoldPrice::factory()->for($user)->create(['price_per_chi' => 15_500_000]);

    Livewire::test(GoldItemShow::class, ['goldItem' => $item])
        ->call('openSell')
        ->set('saleWeightChi', '0,5')
        ->set('saleAmount', 8_000_000)
        ->set('soldOn', '2026-06-01')
        ->call('sell')
        ->assertHasNoErrors()
        ->assertSet('showSell', false)
        ->assertSee('Lịch sử bán');

    $portfolio = app(GoldPortfolio::class)->forUser($user);

    expect($portfolio['totals'])->toBe([
        'weight' => 150,
        'cost' => 22_500_000,
        'value' => 23_250_000,
        'unrealized' => 750_000,
        'realized' => 500_000,
        'unpriced' => 0,
    ]);
});

test('a sale cannot exceed what is left or predate the purchase', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create(['weight_ly' => 100, 'bought_on' => '2026-01-15']);
    GoldSale::factory()->create(['gold_item_id' => $item->id, 'weight_ly' => 60]);
    $sale = ['amount' => 1, 'sold_on' => '2026-02-01'];

    expect(fn () => app(SellGold::class)->handle($item, ['weight_ly' => 41] + $sale))
        ->toThrow(HuiActionException::class, 'vượt quá phần còn lại')
        ->and(fn () => app(SellGold::class)->handle($item, ['weight_ly' => 1, 'amount' => 1, 'sold_on' => '2026-01-14']))
        ->toThrow(HuiActionException::class, 'trước ngày mua');

    Livewire::test(GoldItemShow::class, ['goldItem' => $item])
        ->call('openSell')
        ->set('saleWeightChi', '0,41')
        ->set('saleAmount', 1)
        ->set('soldOn', '2026-02-01')
        ->call('sell')
        ->assertSee('vượt quá phần còn lại');

    app(SellGold::class)->handle($item, ['weight_ly' => 40] + $sale);

    expect(GoldSale::count())->toBe(2);
});

test('an item sold in full moves to the sold-out list', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create(['weight_ly' => 100, 'cost_amount' => 10_000_000]);
    GoldSale::factory()->create(['gold_item_id' => $item->id, 'weight_ly' => 100, 'amount' => 12_000_000]);

    $portfolio = app(GoldPortfolio::class)->forUser($user);

    expect($portfolio['held'])->toBeEmpty()
        ->and($portfolio['soldOut']->sole()->realized)->toBe(2_000_000)
        ->and($portfolio['totals']['realized'])->toBe(2_000_000)
        ->and($portfolio['totals']['weight'])->toBe(0);

    Livewire::test(GoldIndex::class)->assertSee('Đã bán hết')->assertSee('Không còn món nào đang giữ');
    Livewire::test(GoldItemShow::class, ['goldItem' => $item])->assertDontSee('Bán vàng');
});

test('deleting a sale returns the weight to the item', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create(['weight_ly' => 100]);
    $sale = GoldSale::factory()->create(['gold_item_id' => $item->id, 'weight_ly' => 100]);

    Livewire::test(GoldItemShow::class, ['goldItem' => $item])->call('deleteSale', $sale->id);

    expect(GoldSale::count())->toBe(0)
        ->and(app(GoldPortfolio::class)->forUser($user)['totals']['weight'])->toBe(100);
});

test('a sale of another item cannot be deleted from this page', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create();
    $other = GoldItem::factory()->for($user)->create();
    $sale = GoldSale::factory()->create(['gold_item_id' => $other->id]);

    expect(fn () => Livewire::test(GoldItemShow::class, ['goldItem' => $item])->call('deleteSale', $sale->id))
        ->toThrow(ModelNotFoundException::class);

    expect(GoldSale::count())->toBe(1);
});

test('an item with sales cannot be deleted, one without can', function () {
    $this->actingAs($user = User::factory()->create());
    $sold = GoldItem::factory()->for($user)->create();
    GoldSale::factory()->create(['gold_item_id' => $sold->id]);
    $unsold = GoldItem::factory()->for($user)->create();

    expect(fn () => app(DeleteGoldItem::class)->handle($sold))->toThrow(HuiActionException::class);

    Livewire::test(GoldItemShow::class, ['goldItem' => $sold])
        ->call('deleteItem')
        ->assertSet('actionError', 'Món này đã có giao dịch bán, hãy xóa giao dịch bán trước.');

    Livewire::test(GoldItemShow::class, ['goldItem' => $unsold])
        ->call('deleteItem')
        ->assertRedirect(route('gold.index'));

    expect(GoldItem::pluck('id')->all())->toBe([$sold->id]);
});

test('prices are saved per type and re-saving moves the date without a second row', function () {
    $this->actingAs($user = User::factory()->create());
    GoldItem::factory()->for($user)->create(['gold_type' => GoldType::Sjc]);

    $this->travelTo('2026-11-01');
    Livewire::test(GoldIndex::class)->set('prices.sjc', 16_000_000)->call('savePrice', 'sjc')->assertHasNoErrors();

    $this->travelTo('2026-11-05');
    Livewire::test(GoldIndex::class)->assertSet('prices.sjc', 16_000_000)->call('savePrice', 'sjc');

    expect(GoldPrice::sole()->priced_on->toDateString())->toBe('2026-11-05');
});

test('a price must be positive and of a known type', function () {
    $this->actingAs($user = User::factory()->create());
    GoldItem::factory()->for($user)->create(['gold_type' => GoldType::Sjc]);

    Livewire::test(GoldIndex::class)->set('prices.sjc', null)->call('savePrice', 'sjc')->assertHasErrors('price_per_chi');
    Livewire::test(GoldIndex::class)->set('prices.bac', 100)->call('savePrice', 'bac')->assertHasErrors('gold_type');

    expect(GoldPrice::count())->toBe(0);
});

test('items without a price count as unknown, not as zero', function () {
    $this->actingAs($user = User::factory()->create());
    GoldItem::factory()->for($user)->create(['gold_type' => GoldType::Sjc, 'weight_ly' => 100, 'cost_amount' => 15_000_000]);
    GoldItem::factory()->for($user)->create(['gold_type' => GoldType::Vang18k, 'weight_ly' => 100, 'cost_amount' => 9_000_000]);
    GoldPrice::factory()->for($user)->create(['gold_type' => GoldType::Sjc, 'price_per_chi' => 16_000_000]);

    $totals = app(GoldPortfolio::class)->forUser($user)['totals'];

    expect($totals['value'])->toBe(16_000_000)
        ->and($totals['unrealized'])->toBe(1_000_000)
        ->and($totals['unpriced'])->toBe(1);

    Livewire::test(GoldIndex::class)->assertSee('chưa tính 1 món chưa có giá')->assertSee('Chưa có giá');
});

test('backup round-trips the gold ledger into another account', function () {
    $alice = User::factory()->create();
    $item = GoldItem::factory()->for($alice)->create(['name' => 'Nhẫn cưới', 'weight_ly' => 200, 'bought_on' => '2026-01-15']);
    GoldSale::factory()->create(['gold_item_id' => $item->id, 'weight_ly' => 50, 'amount' => 8_000_000]);
    GoldPrice::factory()->for($alice)->create();
    $backup = json_decode(json_encode(app(UserBackup::class)->export($alice)), true);

    $bob = User::factory()->create();
    GoldItem::factory()->for($bob)->create(['name' => 'Món cũ']);
    $this->actingAs($bob);

    $stats = app(RestoreBackup::class)->handle($bob, $backup);

    expect($stats['gold_items'])->toBe(1)
        ->and(GoldItem::sole()->name)->toBe('Nhẫn cưới')
        ->and(GoldSale::sole()->amount)->toBe(8_000_000)
        ->and(GoldPrice::sole()->price_per_chi)->toBe(15_500_000)
        ->and(GoldItem::withoutGlobalScopes()->where('user_id', $alice->id)->count())->toBe(1);
});

test('restoring a file from before the gold ledger keeps the current gold', function () {
    $user = User::factory()->create();
    GoldItem::factory()->for($user)->create();
    $this->actingAs($user);

    app(RestoreBackup::class)->handle($user, ['app' => 'so-hui', 'version' => 1, 'groups' => []]);

    expect(GoldItem::count())->toBe(1);
});

test('a backup that oversells an item is refused and nothing changes', function () {
    $user = User::factory()->create();
    GoldItem::factory()->for($user)->create(['name' => 'Giữ nguyên']);
    $this->actingAs($user);

    $backup = ['app' => 'so-hui', 'version' => 1, 'groups' => [], 'gold' => ['prices' => [], 'items' => [[
        'gold_type' => 'sjc', 'weight_ly' => 100, 'cost_amount' => 1, 'bought_on' => '2026-01-01',
        'sales' => [['weight_ly' => 101, 'amount' => 1, 'sold_on' => '2026-02-01']],
    ]]]];

    expect(fn () => app(RestoreBackup::class)->handle($user, $backup))->toThrow(HuiActionException::class, 'vượt quá');
    expect(GoldItem::sole()->name)->toBe('Giữ nguyên');
});
