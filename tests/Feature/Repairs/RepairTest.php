<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Actions\Repairs\AddRepairRecordPhotos;
use App\Exceptions\HuiActionException;
use App\Livewire\Repairs\RepairIndex;
use App\Livewire\Repairs\RepairItemForm;
use App\Livewire\Repairs\RepairItemShow;
use App\Livewire\Repairs\RepairRecordForm;
use App\Models\RepairItem;
use App\Models\RepairRecord;
use App\Models\RepairRecordPhoto;
use App\Models\User;
use App\Queries\NetWorthReport;
use App\Queries\RepairHistory;
use App\Queries\UserBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Sổ sửa chữa (document/08-sua-chua.md).
 */

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-07 09:00:00');
});

function repairPhotoFor(RepairRecord $record): RepairRecordPhoto
{
    app(AddRepairRecordPhotos::class)->handle($record, [UploadedFile::fake()->image('hoa-don.jpg')]);

    return RepairRecordPhoto::query()->where('repair_record_id', $record->id)->latest('id')->firstOrFail();
}

test('both roles get a repair area with its own navigation', function (User $user) {
    $this->actingAs($user);

    $this->get(route('repairs.index'))->assertOk()
        ->assertSeeInOrder(['Tổng', 'Hụi', 'Vàng', 'Sửa chữa', 'Chưa có món đồ nào', 'Món đồ', 'Cài đặt'])
        ->assertDontSee('Dây hụi')
        ->assertDontSee(route('gold.prices'));

    $this->get(route('settings'))->assertOk()->assertSee(route('repairs.index'))->assertDontSee('Dây hụi');
})->with([
    'member' => fn () => User::factory()->create(),
    'host' => fn () => User::factory()->host()->create(),
]);

test('guests are sent to the login page', function () {
    $this->get(route('repairs.index'))->assertRedirect(route('login'));
});

test('an item and a record are saved with every optional field', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(RepairItemForm::class)
        ->set('name', ' Xe SH ')
        ->set('tracksKm', true)
        ->call('save')
        ->assertHasNoErrors();

    $item = RepairItem::sole();
    expect($item->name)->toBe('Xe SH')->and($item->tracks_km)->toBeTrue()->and($item->user_id)->toBe($user->id);

    Livewire::test(RepairRecordForm::class, ['repairItem' => $item])
        ->set('repairedOn', '2026-11-05')
        ->set('part', ' Lốp sau ')
        ->set('amount', 900_000)
        ->set('place', 'Tiệm Hùng')
        ->set('warrantyUntil', '2027-11-05')
        ->set('odometerKm', '12.500')
        ->set('note', 'Lốp Michelin')
        ->set('photos', [UploadedFile::fake()->image('hoa-don.jpg')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('repairs.show', $item));

    $record = RepairRecord::sole();
    expect($record->part)->toBe('Lốp sau')
        ->and($record->amount)->toBe(900_000)
        ->and($record->place)->toBe('Tiệm Hùng')
        ->and($record->warranty_until->toDateString())->toBe('2027-11-05')
        ->and($record->odometer_km)->toBe(12_500)
        ->and($record->photos)->toHaveCount(1)
        ->and($record->photos[0]->path)->toStartWith("repairs/{$user->id}/");
});

test('an existing record can be edited without losing its fields', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    $record = RepairRecord::factory()->create(['repair_item_id' => $item->id, 'odometer_km' => 500, 'warranty_until' => '2027-01-01']);

    Livewire::test(RepairRecordForm::class, ['repairRecord' => $record])
        ->assertSet('odometerKm', '500')
        ->set('amount', 1_000)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->refresh()->amount)->toBe(1_000)
        ->and($record->odometer_km)->toBe(500)
        ->and($record->warranty_until->toDateString())->toBe('2027-01-01')
        ->and(RepairRecord::query()->count())->toBe(1);
});

test('free repairs are allowed but bad input is rejected', function (array $input, string $errorField) {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();

    $form = Livewire::test(RepairRecordForm::class, ['repairItem' => $item])->set('part', 'Pin')->set('amount', 0);

    foreach ($input as $property => $value) {
        $form->set($property, $value);
    }

    $form->call('save')->assertHasErrors($errorField);
    expect(RepairRecord::query()->count())->toBe(0);
})->with([
    'no part' => [['part' => ' '], 'part'],
    'no amount' => [['amount' => null], 'amount'],
    'future date' => [['repairedOn' => '2026-11-08'], 'repaired_on'],
    'warranty before repair' => [['repairedOn' => '2026-11-05', 'warrantyUntil' => '2026-11-04'], 'warranty_until'],
    'half-typed warranty' => [['warrantyUntil' => 'ngay-khong-hop-le'], 'warranty_until'],
]);

test('a free repair saves', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();

    Livewire::test(RepairRecordForm::class, ['repairItem' => $item])
        ->set('part', 'Pin')->set('amount', 0)->set('warrantyUntil', '')
        ->call('save')->assertHasNoErrors();

    expect(RepairRecord::sole()->warranty_until)->toBeNull();
});

test('the km field shows only for vehicles or records that already have a reading', function () {
    $this->actingAs($user = User::factory()->create());
    $phone = RepairItem::factory()->for($user)->create(['tracks_km' => false]);
    $bike = RepairItem::factory()->for($user)->create(['tracks_km' => true]);
    $oldReading = RepairRecord::factory()->create(['repair_item_id' => $phone->id, 'odometer_km' => 100]);

    Livewire::test(RepairRecordForm::class, ['repairItem' => $phone])->assertDontSee('Số km trên xe');
    Livewire::test(RepairRecordForm::class, ['repairItem' => $bike])->assertSee('Số km trên xe');
    Livewire::test(RepairRecordForm::class, ['repairRecord' => $oldReading])->assertSee('Số km trên xe');
});

test('history pairs records of the same part and works out how long the previous one lasted', function () {
    $this->actingAs($user = User::factory()->create());
    $bike = RepairItem::factory()->for($user)->create(['tracks_km' => true]);
    $make = fn (string $on, string $part, int $amount, ?int $km = null) => RepairRecord::factory()->create([
        'repair_item_id' => $bike->id, 'repaired_on' => $on, 'part' => $part, 'amount' => $amount, 'odometer_km' => $km,
    ]);

    $make('2024-11-05', 'Lốp sau', 850_000, 10_000);
    $make('2026-03-02', 'Thay nhớt', 150_000);
    $make('2026-11-05', ' lốp  SAU ', 900_000, 22_500);

    $history = app(RepairHistory::class)->forItem($bike);

    expect($history['total'])->toBe(1_900_000)
        ->and(array_map(fn ($entry) => [$entry->record->amount, $entry->lastedFor, $entry->lastedKm], $history['entries']))->toBe([
            [900_000, '2 năm', 12_500],
            [150_000, null, null],
            [850_000, null, null],
        ])
        ->and(array_map(fn ($part) => [$part->name, $part->count, $part->total, $part->inUseFor], $history['parts']))->toBe([
            [' lốp  SAU ', 2, 1_750_000, '2 ngày'],
            ['Thay nhớt', 1, 150_000, '8 tháng'],
        ]);

    Livewire::test(RepairItemShow::class, ['repairItem' => $bike])
        ->assertSee('Lần trước dùng được 2 năm · 12.500 km');
});

test('other items do not count towards durability', function () {
    $this->actingAs($user = User::factory()->create());
    $first = RepairItem::factory()->for($user)->create();
    $second = RepairItem::factory()->for($user)->create();
    RepairRecord::factory()->create(['repair_item_id' => $first->id, 'repaired_on' => '2024-01-01', 'part' => 'Pin']);
    RepairRecord::factory()->create(['repair_item_id' => $second->id, 'repaired_on' => '2026-01-01', 'part' => 'Pin']);

    expect(app(RepairHistory::class)->forItem($second)['entries'][0]->lastedFor)->toBeNull();
});

test('records of the same part are paired by repair date, not by entry order', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2026-06-01', 'part' => 'Pin']);
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2025-06-01', 'part' => 'Pin']);

    $entries = app(RepairHistory::class)->forItem($item)['entries'];

    expect($entries[0]->record->repaired_on->toDateString())->toBe('2026-06-01')
        ->and($entries[0]->lastedFor)->toBe('1 năm')
        ->and($entries[1]->lastedFor)->toBeNull();
});

test('earlier part names are suggested only on the same item', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    $other = RepairItem::factory()->for($user)->create();
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'part' => 'pin']);
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'part' => 'Pin', 'repaired_on' => '2026-02-01']);
    RepairRecord::factory()->create(['repair_item_id' => $other->id, 'part' => 'Màn hình']);

    expect(app(RepairHistory::class)->partNames($item))->toBe(['Pin']);
});

test('warranty is still valid on its last day and ends the day after', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2026-01-01', 'warranty_until' => '2026-11-07']);
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2026-01-01', 'part' => 'Pin', 'warranty_until' => '2026-11-06']);

    Livewire::test(RepairItemShow::class, ['repairItem' => $item])
        ->assertSee('Còn bảo hành đến 07/11/2026')
        ->assertSee('Hết bảo hành từ 06/11/2026');
});

test('the overview totals only this account, and the year total only this year', function () {
    $this->actingAs(User::factory()->create());
    RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->create()->id, 'repaired_on' => '2026-02-01', 'amount' => 7_000]);

    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create(['name' => 'Laptop']);
    RepairItem::factory()->for($user)->create(['name' => 'Chưa sửa']);
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2026-01-01', 'amount' => 300]);
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2025-12-31', 'amount' => 50]);

    $overview = app(RepairHistory::class)->forUser($user);

    expect($overview['total'])->toBe(350)
        ->and($overview['yearTotal'])->toBe(300)
        ->and($overview['items']->pluck('name')->all())->toBe(['Laptop', 'Chưa sửa']);

    Livewire::test(RepairIndex::class)->assertSeeInOrder(['Laptop', '2 lần', 'Chưa sửa', 'Chưa có lần sửa nào']);
});

test('repair costs are not part of net worth', function () {
    $this->actingAs($user = User::factory()->create());
    $before = app(NetWorthReport::class)->forUser($user);

    RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id, 'amount' => 5_000_000]);

    expect(app(NetWorthReport::class)->forUser($user))->toEqual($before);
});

test('one account cannot reach another account\'s repairs or photos', function () {
    $this->actingAs($owner = User::factory()->create());
    $item = RepairItem::factory()->for($owner)->create();
    $record = RepairRecord::factory()->create(['repair_item_id' => $item->id]);
    $photo = repairPhotoFor($record);

    $this->actingAs(User::factory()->create());

    $this->get(route('repairs.show', $item->id))->assertNotFound();
    $this->get(route('repairs.edit', $item->id))->assertNotFound();
    $this->get(route('repairs.records.create', $item->id))->assertNotFound();
    $this->get(route('repairs.records.edit', $record->id))->assertNotFound();
    $this->get(route('repairs.photos.show', $photo->id))->assertNotFound();
});

test('the owner opens a photo privately', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = repairPhotoFor(RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id]));

    $response = $this->get(route('repairs.photos.show', $photo))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    auth()->logout();
    $this->get(route('repairs.photos.show', $photo))->assertRedirect(route('login'));
});

test('a record holds at most five photos', function () {
    $this->actingAs($user = User::factory()->create());
    $record = RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id]);
    $files = fn (int $count) => array_map(fn () => UploadedFile::fake()->image('a.jpg'), range(1, $count));

    app(AddRepairRecordPhotos::class)->handle($record, $files(4));

    expect(fn () => app(AddRepairRecordPhotos::class)->handle($record, $files(2)))
        ->toThrow(HuiActionException::class, 'tối đa 5 ảnh');
    expect($record->photos()->count())->toBe(4)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(4);

    Livewire::test(RepairRecordForm::class, ['repairRecord' => $record])
        ->set('photos', $files(2))
        ->call('save')
        ->assertHasErrors('photos');
});

test('deleting a photo removes the file', function () {
    $this->actingAs($user = User::factory()->create());
    $record = RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id]);
    $photo = repairPhotoFor($record);

    Livewire::test(RepairRecordForm::class, ['repairRecord' => $record])->call('deletePhoto', $photo->id);

    expect(RepairRecordPhoto::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($photo->path);
});

test('deleting a record removes its photos but keeps the item and other records', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    $doomed = RepairRecord::factory()->create(['repair_item_id' => $item->id]);
    $kept = RepairRecord::factory()->create(['repair_item_id' => $item->id]);
    $doomedPhoto = repairPhotoFor($doomed);
    $keptPhoto = repairPhotoFor($kept);

    Livewire::test(RepairRecordForm::class, ['repairRecord' => $doomed])
        ->call('deleteRecord')
        ->assertRedirect(route('repairs.show', $item));

    expect(RepairRecord::query()->pluck('id')->all())->toBe([$kept->id]);
    Storage::disk('local')->assertMissing($doomedPhoto->path);
    Storage::disk('local')->assertExists($keptPhoto->path);
});

test('deleting an item removes its records and photo files only', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    $other = RepairItem::factory()->for($user)->create();
    $photo = repairPhotoFor(RepairRecord::factory()->create(['repair_item_id' => $item->id]));
    $otherPhoto = repairPhotoFor(RepairRecord::factory()->create(['repair_item_id' => $other->id]));

    Livewire::test(RepairItemShow::class, ['repairItem' => $item])
        ->call('deleteItem')
        ->assertRedirect(route('repairs.index'));

    expect(RepairItem::query()->pluck('id')->all())->toBe([$other->id])
        ->and(RepairRecord::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing($photo->path);
    Storage::disk('local')->assertExists($otherPhoto->path);
});

test('deleting an account removes its photo folder', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = repairPhotoFor(RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id]));

    $user->delete();

    Storage::disk('local')->assertMissing($photo->path);
});

test('a backup carries the repair book into another account', function () {
    $this->actingAs($alice = User::factory()->create());
    $item = RepairItem::factory()->for($alice)->create(['tracks_km' => true, 'note' => 'Xe chính']);
    RepairRecord::factory()->create([
        'repair_item_id' => $item->id, 'repaired_on' => '2026-01-02', 'part' => 'Nhớt', 'amount' => 150_000,
        'place' => 'Tiệm Hùng', 'warranty_until' => '2026-02-02', 'odometer_km' => 900, 'note' => 'Castrol',
    ]);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($alice)), true);

    $this->actingAs($bob = User::factory()->create());
    $stats = app(RestoreBackup::class)->handle($bob, $backup);

    expect($stats['repair_items'])->toBe(1)
        ->and(app(UserBackup::class)->export($bob)['repairs'])->toBe($backup['repairs'])
        ->and(RepairItem::sole()->user_id)->toBe($bob->id)
        ->and(RepairRecord::sole()->user_id)->toBe($bob->id);
});

test('restoring a file with a repair book replaces it and removes old photos', function () {
    $this->actingAs($user = User::factory()->create());
    $backup = json_decode(json_encode(app(UserBackup::class)->export($user)), true);
    $photo = repairPhotoFor(RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id]));

    app(RestoreBackup::class)->handle($user, $backup);

    expect(RepairItem::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($photo->path);
});

test('restoring an older file without a repair book leaves it alone', function () {
    $this->actingAs($user = User::factory()->create());
    $backup = json_decode(json_encode(app(UserBackup::class)->export($user)), true);
    unset($backup['repairs']);
    $photo = repairPhotoFor(RepairRecord::factory()->create(['repair_item_id' => RepairItem::factory()->for($user)->create()->id]));

    app(RestoreBackup::class)->handle($user, $backup);

    expect(RepairItem::query()->count())->toBe(1);
    Storage::disk('local')->assertExists($photo->path);
});

test('a backup with a warranty before its repair date is rejected before anything is deleted', function () {
    $this->actingAs($user = User::factory()->create());
    $item = RepairItem::factory()->for($user)->create();
    RepairRecord::factory()->create(['repair_item_id' => $item->id, 'repaired_on' => '2026-02-02']);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($user)), true);
    $backup['repairs']['items'][0]['records'][0]['warranty_until'] = '2026-02-01';

    expect(fn () => app(RestoreBackup::class)->handle($user, $backup))->toThrow(HuiActionException::class, 'ngày bảo hành');
    expect(RepairItem::query()->count())->toBe(1);
});
