<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Actions\Health\AddHealthRecordPhotos;
use App\Enums\HealthRecordKind;
use App\Exceptions\HuiActionException;
use App\Livewire\Family\HealthIndex;
use App\Livewire\Family\PersonForm;
use App\Livewire\Family\PersonShow;
use App\Livewire\Family\RecordForm;
use App\Models\HealthPerson;
use App\Models\HealthRecord;
use App\Models\HealthRecordPhoto;
use App\Models\User;
use App\Queries\HealthBook;
use App\Queries\NetWorthReport;
use App\Queries\UserBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Sổ sức khỏe (document/09-gia-dinh.md GD-04 – GD-08).
 */

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-12 09:00:00');
});

function healthPhotoFor(HealthRecord $record): HealthRecordPhoto
{
    app(AddHealthRecordPhotos::class)->handle($record, [UploadedFile::fake()->image('don-thuoc.jpg')]);

    return HealthRecordPhoto::query()->where('health_record_id', $record->id)->latest('id')->firstOrFail();
}

test('a person and a visit are saved with every optional field', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(PersonForm::class)
        ->set('name', ' Bé Na ')
        ->set('birthYear', '2020')
        ->set('note', 'Dị ứng penicillin')
        ->call('save')
        ->assertHasNoErrors();

    $person = HealthPerson::sole();
    expect([$person->name, $person->birth_year, $person->user_id])->toBe(['Bé Na', 2020, $user->id]);

    Livewire::test(RecordForm::class, ['healthPerson' => $person])
        ->set('visitedOn', '2026-11-10')
        ->set('kind', 'tiem')
        ->set('title', ' Vắc xin cúm ')
        ->set('place', 'VNVC')
        ->set('amount', 350_000)
        ->set('followUpOn', '2027-11-10')
        ->set('note', 'Mũi nhắc hằng năm')
        ->set('photos', [UploadedFile::fake()->image('so-tiem.jpg')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('family.health.show', $person));

    $record = HealthRecord::sole();
    expect($record->kind)->toBe(HealthRecordKind::Tiem)
        ->and($record->title)->toBe('Vắc xin cúm')
        ->and($record->amount)->toBe(350_000)
        ->and($record->follow_up_on->toDateString())->toBe('2027-11-10')
        ->and($record->photos)->toHaveCount(1)
        ->and($record->photos[0]->path)->toStartWith("health/{$user->id}/");

    Livewire::test(PersonShow::class, ['healthPerson' => $person])
        ->assertSee('6 tuổi (sinh năm 2020)')
        ->assertSee('Hẹn tái khám')
        ->assertSee('10/11/2027');
});

test('a visit without a cost saves, and editing keeps its fields', function () {
    $this->actingAs($user = User::factory()->create());
    $person = HealthPerson::factory()->for($user)->create();

    Livewire::test(RecordForm::class, ['healthPerson' => $person])->set('title', 'Khám tổng quát')->call('save')->assertHasNoErrors();

    $record = HealthRecord::sole();
    expect($record->amount)->toBeNull()->and($record->visited_on->toDateString())->toBe('2026-11-12');

    Livewire::test(RecordForm::class, ['healthRecord' => $record])
        ->assertSet('title', 'Khám tổng quát')
        ->set('amount', 100_000)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->refresh()->amount)->toBe(100_000)->and(HealthRecord::query()->count())->toBe(1);
});

test('bad input is rejected', function (array $input, string $errorField) {
    $this->actingAs($user = User::factory()->create());
    $person = HealthPerson::factory()->for($user)->create();

    $form = Livewire::test(RecordForm::class, ['healthPerson' => $person])->set('title', 'Khám');

    foreach ($input as $property => $value) {
        $form->set($property, $value);
    }

    $form->call('save')->assertHasErrors($errorField);
    expect(HealthRecord::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ' '], 'title'],
    'future visit' => [['visitedOn' => '2026-11-13'], 'visited_on'],
    'follow-up before visit' => [['visitedOn' => '2026-11-10', 'followUpOn' => '2026-11-09'], 'follow_up_on'],
    'unknown kind' => [['kind' => 'mo'], 'kind'],
]);

test('a birth year must be a past year', function (string $birthYear) {
    $this->actingAs(User::factory()->create());

    Livewire::test(PersonForm::class)->set('name', 'Bố')->set('birthYear', $birthYear)->call('save')->assertHasErrors('birth_year');
})->with(['2027', '1850', 'abc']);

test('the overview lists upcoming follow-ups and this year\'s costs for this account only', function () {
    $this->actingAs(User::factory()->create());
    HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->create()->id, 'amount' => 7_000, 'follow_up_on' => '2026-11-20']);

    $this->actingAs($user = User::factory()->create());
    $dad = HealthPerson::factory()->for($user)->create(['name' => 'Bố']);
    $kid = HealthPerson::factory()->for($user)->create(['name' => 'Bé Na']);
    HealthPerson::factory()->for($user)->create(['name' => 'Mẹ']);
    HealthRecord::factory()->create(['health_person_id' => $dad->id, 'visited_on' => '2026-11-01', 'amount' => 300, 'follow_up_on' => '2026-11-15', 'title' => 'Khám tim']);
    HealthRecord::factory()->create(['health_person_id' => $kid->id, 'visited_on' => '2026-03-01', 'amount' => null, 'follow_up_on' => '2026-11-12', 'title' => 'Khám răng']);
    HealthRecord::factory()->create(['health_person_id' => $kid->id, 'visited_on' => '2025-12-31', 'amount' => 50, 'follow_up_on' => '2026-01-05']);

    $overview = app(HealthBook::class)->forUser($user);

    expect($overview['yearTotal'])->toBe(300)
        ->and($overview['people']->pluck('name')->all())->toBe(['Bố', 'Bé Na', 'Mẹ'])
        ->and($overview['followUps']->pluck('title')->all())->toBe(['Khám răng', 'Khám tim']);

    Livewire::test(HealthIndex::class)->assertSeeInOrder([
        'Lịch hẹn sắp tới', 'Bé Na', 'Khám răng', 'Hôm nay', 'Bố', 'Khám tim', 'Còn 3 ngày',
        'Bố', '1 lần', 'Bé Na', '2 lần', 'Mẹ', 'Chưa có lần khám nào',
    ]);
});

test('health costs are not part of net worth', function () {
    $this->actingAs($user = User::factory()->create());
    $before = app(NetWorthReport::class)->forUser($user);

    HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($user)->create()->id, 'amount' => 5_000_000]);

    expect(app(NetWorthReport::class)->forUser($user))->toEqual($before);
});

test('one account cannot reach another account\'s health book or photos', function () {
    $this->actingAs($owner = User::factory()->create());
    $person = HealthPerson::factory()->for($owner)->create();
    $record = HealthRecord::factory()->create(['health_person_id' => $person->id]);
    $photo = healthPhotoFor($record);

    $this->actingAs(User::factory()->create());

    $this->get(route('family.health.show', $person->id))->assertNotFound();
    $this->get(route('family.health.people.edit', $person->id))->assertNotFound();
    $this->get(route('family.health.records.create', $person->id))->assertNotFound();
    $this->get(route('family.health.records.edit', $record->id))->assertNotFound();
    $this->get(route('family.health.photos.show', $photo->id))->assertNotFound();
});

test('the owner opens a photo privately', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = healthPhotoFor(HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($user)->create()->id]));

    $response = $this->get(route('family.health.photos.show', $photo))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});

test('a visit holds at most five photos', function () {
    $this->actingAs($user = User::factory()->create());
    $record = HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($user)->create()->id]);
    $files = fn (int $count) => array_map(fn () => UploadedFile::fake()->image('a.jpg'), range(1, $count));

    app(AddHealthRecordPhotos::class)->handle($record, $files(4));

    expect(fn () => app(AddHealthRecordPhotos::class)->handle($record, $files(2)))->toThrow(HuiActionException::class, 'tối đa 5 ảnh');

    Livewire::test(RecordForm::class, ['healthRecord' => $record])->set('photos', $files(2))->call('save')->assertHasErrors('photos');
    expect($record->photos()->count())->toBe(4);
});

test('deleting a photo, a visit or a person removes the files', function () {
    $this->actingAs($user = User::factory()->create());
    $person = HealthPerson::factory()->for($user)->create();
    $other = HealthPerson::factory()->for($user)->create();
    $first = HealthRecord::factory()->create(['health_person_id' => $person->id]);
    $second = HealthRecord::factory()->create(['health_person_id' => $person->id]);
    $firstPhoto = healthPhotoFor($first);
    $secondPhoto = healthPhotoFor($second);
    $otherPhoto = healthPhotoFor(HealthRecord::factory()->create(['health_person_id' => $other->id]));

    Livewire::test(RecordForm::class, ['healthRecord' => $first])->call('deletePhoto', $firstPhoto->id);
    Storage::disk('local')->assertMissing($firstPhoto->path);

    Livewire::test(RecordForm::class, ['healthRecord' => $first])->call('deleteRecord')->assertRedirect(route('family.health.show', $person));
    expect(HealthRecord::query()->whereKey($first->id)->exists())->toBeFalse();

    Livewire::test(PersonShow::class, ['healthPerson' => $person])->call('deletePerson')->assertRedirect(route('family.health.index'));

    expect(HealthPerson::query()->pluck('id')->all())->toBe([$other->id])
        ->and(HealthRecord::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing($secondPhoto->path);
    Storage::disk('local')->assertExists($otherPhoto->path);
});

test('deleting an account removes its health photo folder', function () {
    $this->actingAs($user = User::factory()->create());
    $photo = healthPhotoFor(HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($user)->create()->id]));

    $user->delete();

    Storage::disk('local')->assertMissing($photo->path);
});

test('a backup carries the health book into another account and old photos go on restore', function () {
    $this->actingAs($alice = User::factory()->create());
    $person = HealthPerson::factory()->for($alice)->create(['birth_year' => 1960, 'note' => 'Huyết áp cao']);
    HealthRecord::factory()->create([
        'health_person_id' => $person->id, 'visited_on' => '2026-10-01', 'kind' => HealthRecordKind::XetNghiem,
        'title' => 'Xét nghiệm máu', 'place' => 'Medlatec', 'amount' => null, 'follow_up_on' => '2026-12-01', 'note' => 'Mỡ máu cao',
    ]);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($alice)), true);

    $this->actingAs($bob = User::factory()->create());
    $bobPhoto = healthPhotoFor(HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($bob)->create()->id]));
    $stats = app(RestoreBackup::class)->handle($bob, $backup);

    expect($stats['health_people'])->toBe(1)
        ->and(app(UserBackup::class)->export($bob)['family'])->toBe($backup['family'])
        ->and(HealthPerson::sole()->user_id)->toBe($bob->id)
        ->and(HealthRecord::sole()->user_id)->toBe($bob->id);
    Storage::disk('local')->assertMissing($bobPhoto->path);
});

test('a backup with a follow-up before its visit is rejected before anything is deleted', function () {
    $this->actingAs($user = User::factory()->create());
    HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($user)->create()->id, 'visited_on' => '2026-02-02']);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($user)), true);
    $backup['family']['people'][0]['records'][0]['follow_up_on'] = '2026-02-01';

    expect(fn () => app(RestoreBackup::class)->handle($user, $backup))->toThrow(HuiActionException::class, 'ngày tái khám');
    expect(HealthRecord::query()->count())->toBe(1);
});
