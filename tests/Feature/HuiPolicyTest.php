<?php

declare(strict_types=1);

use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('owners may act on their rows and other users may not', function (string $modelClass, array $abilities) {
    $owner = User::factory()->create();
    $model = match ($modelClass) {
        HuiGroup::class => HuiGroup::factory()->for($owner)->create(),
        MySlot::class => MySlot::factory()->for(HuiGroup::factory()->for($owner), 'group')->create(),
        HuiRound::class => HuiRound::factory()->for(HuiGroup::factory()->for($owner), 'group')->create(),
        CashEntry::class => CashEntry::factory()->for(MySlot::factory()->for(HuiGroup::factory()->for($owner), 'group'), 'slot')->create(),
    };
    $otherUser = User::factory()->create();

    foreach ($abilities as $ability) {
        expect(Gate::forUser($owner)->allows($ability, $model))->toBeTrue("owner should be allowed to {$ability}")
            ->and(Gate::forUser($otherUser)->allows($ability, $model))->toBeFalse("other user should not be allowed to {$ability}");
    }
})->with([
    'group' => [HuiGroup::class, ['view', 'update', 'delete']],
    'slot' => [MySlot::class, ['view', 'update', 'delete']],
    'round' => [HuiRound::class, ['view', 'update']],
    'cash entry' => [CashEntry::class, ['view', 'update', 'delete']],
]);

test('rounds cannot be deleted because they are generated with the group', function () {
    $owner = User::factory()->create();
    $round = HuiRound::factory()->for(HuiGroup::factory()->for($owner), 'group')->create();

    expect(Gate::forUser($owner)->allows('delete', $round))->toBeFalse();
});

test('a slot that has already won cannot be deleted', function () {
    $owner = User::factory()->create();
    $slot = MySlot::factory()->for(HuiGroup::factory()->for($owner), 'group')->create();
    HuiRound::factory()->wonBy($slot)->create();

    $response = Gate::forUser($owner)->inspect('delete', $slot);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('Tay này đã hốt nên không thể xóa.');
});

test('another user\'s slot is reported as not found on delete', function () {
    $slot = MySlot::factory()->create();

    $response = Gate::forUser(User::factory()->create())->inspect('delete', $slot);

    expect($response->status())->toBe(404);
});
