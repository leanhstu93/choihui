<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Actions\Gold\AddGoldItemPhotos;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StorePhotosRequest;
use App\Livewire\Gold\GoldItemForm;
use App\Livewire\Gold\GoldItemShow;
use App\Models\GoldItem;
use App\Models\GoldItemPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Ảnh món vàng (document/06-vang.md V13).
 */

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function goldPhotoFor(GoldItem $item): GoldItemPhoto
{
    app(AddGoldItemPhotos::class)->handle($item, [UploadedFile::fake()->image('anh.jpg')]);

    return GoldItemPhoto::query()->where('gold_item_id', $item->id)->latest('id')->firstOrFail();
}

test('photos added with a new item are stored privately and shown on its page', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(GoldItemForm::class)
        ->set('weightChi', '1')
        ->set('costAmount', 1000)
        ->set('photos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')])
        ->call('save')
        ->assertHasNoErrors();

    $item = GoldItem::sole();

    expect($item->photos)->toHaveCount(2);
    Storage::disk('local')->assertExists($item->photos[0]->path);
    expect($item->photos[0]->path)->toStartWith("gold/{$user->id}/")->not->toContain('a.jpg');
    expect(Storage::disk('public')->allFiles())->toBe([]);

    Livewire::test(GoldItemShow::class, ['goldItem' => $item])->assertSee(route('gold.photos.show', $item->photos[0]));
});

test('the owner can open a photo with safe headers', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = goldPhotoFor(GoldItem::factory()->for($user)->create());

    $response = $this->get(route('gold.photos.show', $photo))->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});

test('nobody else can open a photo', function () {
    $this->actingAs($owner = User::factory()->create());
    $photo = goldPhotoFor(GoldItem::factory()->for($owner)->create());
    auth()->logout();

    $this->get(route('gold.photos.show', $photo))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    $this->get(route('gold.photos.show', $photo))->assertNotFound();
});

test('the policy refuses another account even when the scope is bypassed', function () {
    $photo = GoldItemPhoto::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('view', $photo))->toBeFalse()
        ->and($stranger->can('delete', $photo))->toBeFalse();
});

test('anything that is not a real photo is refused', function (UploadedFile $file) {
    $this->actingAs(User::factory()->create());

    Livewire::test(GoldItemForm::class)
        ->set('weightChi', '1')
        ->set('costAmount', 1000)
        ->set('photos', [$file])
        ->call('save')
        ->assertHasErrors('photos.0');

    expect(GoldItem::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('gold'))->toBe([]);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('phieu.pdf', 100, 'application/pdf'),
    'svg' => fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'too large' => fn () => UploadedFile::fake()->image('to.jpg')->size(9000),
]);

test('a file renamed to .jpg is judged by its content, not its name', function () {
    $path = tempnam(sys_get_temp_dir(), 'gold');
    file_put_contents($path, '<?php echo 1;');
    $file = new UploadedFile($path, 'anh.jpg', 'image/jpeg', null, true);

    expect(StorePhotosRequest::makeValidator(['photos' => [$file]], 5, 5)->fails())->toBeTrue();

    unlink($path);
});

test('an item holds at most five photos counting the saved ones', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create();
    app(AddGoldItemPhotos::class)->handle($item, array_map(fn () => UploadedFile::fake()->image('a.jpg'), range(1, 4)));

    Livewire::test(GoldItemForm::class, ['goldItem' => $item])
        ->set('photos', [UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')])
        ->call('save')
        ->assertHasErrors('photos');

    expect(fn () => app(AddGoldItemPhotos::class)->handle($item, [UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')]))
        ->toThrow(HuiActionException::class, 'tối đa 5 ảnh');

    expect($item->photos()->count())->toBe(4)
        ->and(Storage::disk('local')->allFiles("gold/{$user->id}"))->toHaveCount(4);
});

test('deleting a photo removes the row and the file, and only from its own item', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create();
    $photo = goldPhotoFor($item);
    $other = goldPhotoFor(GoldItem::factory()->for($user)->create());

    expect(fn () => Livewire::test(GoldItemForm::class, ['goldItem' => $item])->call('deletePhoto', $other->id))
        ->toThrow(ModelNotFoundException::class);

    Livewire::test(GoldItemForm::class, ['goldItem' => $item])->call('deletePhoto', $photo->id);

    expect(GoldItemPhoto::pluck('id')->all())->toBe([$other->id]);
    Storage::disk('local')->assertMissing($photo->path);
    Storage::disk('local')->assertExists($other->path);
});

test('deleting an item removes its files', function () {
    $this->actingAs($user = User::factory()->create());
    $item = GoldItem::factory()->for($user)->create();
    $photo = goldPhotoFor($item);

    Livewire::test(GoldItemShow::class, ['goldItem' => $item])->call('deleteItem');

    Storage::disk('local')->assertMissing($photo->path);
});

test('deleting an account removes its files and nobody elses', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = goldPhotoFor(GoldItem::factory()->for($user)->create());
    $this->actingAs($other = User::factory()->create());
    $kept = goldPhotoFor(GoldItem::factory()->for($other)->create());

    $user->delete();

    Storage::disk('local')->assertMissing($photo->path);
    Storage::disk('local')->assertExists($kept->path);
});

test('a restore that replaces the gold ledger removes old photos, an old file keeps them', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = goldPhotoFor(GoldItem::factory()->for($user)->create());

    app(RestoreBackup::class)->handle($user, ['app' => 'so-hui', 'version' => 1, 'groups' => []]);
    Storage::disk('local')->assertExists($photo->path);

    app(RestoreBackup::class)->handle($user, ['app' => 'so-hui', 'version' => 1, 'groups' => [], 'gold' => ['items' => [], 'prices' => []]]);
    Storage::disk('local')->assertMissing($photo->path);
    expect(GoldItemPhoto::count())->toBe(0);
});
