<?php

declare(strict_types=1);

use App\Actions\Host\MarkCollected;
use App\Actions\Host\RecordHostRoundResult;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Admin\UserShow;
use App\Models\AdminLog;
use App\Models\HuiGroup;
use App\Models\User;
use App\Queries\AdminStats;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;

/*
 * Trang quản trị (document/05-quan-tri.md).
 */

beforeEach(function () {
    config(['hui.admin_emails' => ['admin@example.com']]);
});

function admin(): User
{
    return User::factory()->create(['email' => 'Admin@Example.com']);
}

/**
 * Tạo dây cho người dùng khác rồi đăng xuất, như dữ liệu thật của người khác.
 */
function groupOwnedBy(User $user, Closure $create): HuiGroup
{
    Auth::login($user);
    $group = $create();
    Auth::logout();

    return $group;
}

test('admin pages are hidden behind a 404 for everyone but admins', function (Closure $url) {
    $owner = User::factory()->create();
    $group = groupOwnedBy($owner, fn () => createHuiGroup());
    $url = $url($owner, $group);

    $this->get($url)->assertRedirect(route('login'));

    foreach ([User::factory()->create(), User::factory()->host()->create()] as $user) {
        $this->actingAs($user)->get($url)->assertNotFound();
    }

    $this->actingAs(admin())->get($url)->assertOk();
})->with([
    'dashboard' => [fn () => route('admin.dashboard')],
    'users' => [fn () => route('admin.users.index')],
    'user' => [fn (User $owner) => route('admin.users.show', $owner)],
    'group' => [fn (User $owner, HuiGroup $group) => route('admin.groups.show', $group->id)],
    'logs' => [fn () => route('admin.logs')],
]);

test('admin components refuse Livewire calls from non-admins', function () {
    $target = User::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(UserShow::class, ['user' => $target])
        ->assertNotFound();

    expect(AdminLog::count())->toBe(0);
});

test('only admins see the admin link in the menu', function () {
    $this->actingAs(User::factory()->create())->get(route('settings'))->assertDontSee(route('admin.dashboard'));
    $this->actingAs(admin())->get(route('settings'))->assertSee(route('admin.dashboard'));
});

test('admins see a member group with its entries and the view is logged', function () {
    $owner = User::factory()->create(['name' => 'Chị Lan']);
    $group = groupOwnedBy($owner, fn () => createHuiGroup(['name' => 'Hụi 1tr cô Hoa'], ['Tay của Lan']));
    $this->actingAs($admin = admin());

    $this->get(route('admin.users.show', $owner))->assertOk()->assertSee('Hụi 1tr cô Hoa');
    $this->get(route('admin.groups.show', $group->id))->assertOk()
        ->assertSee(['Hụi 1tr cô Hoa', 'Chị Lan', 'Tay của Lan', 'Kỳ 10']);

    expect(AdminLog::query()->orderBy('id')->get()->map(fn (AdminLog $log) => [$log->action, $log->user_id, $log->admin_id]))
        ->toEqual(collect([[AdminAction::ViewUser, $owner->id, $admin->id], [AdminAction::ViewGroup, $owner->id, $admin->id]]));
});

test('admins see a host group with members, collections and status', function () {
    $owner = User::factory()->host()->create();
    $group = groupOwnedBy($owner, function () {
        $group = createHostGroup(['total_slots' => 3, 'start_date' => now()->subMonth()->toDateString()]);
        $round = $group->rounds[0];
        app(RecordHostRoundResult::class)->handle($round, 0, $group->hostSlots[0]);
        $entry = $round->hostEntries()->whereHas('hostSlot', fn ($query) => $query->where('slot_no', 2))->sole();
        app(MarkCollected::class)->handle($entry, 400_000, $round->open_date->toDateTimeImmutable());

        return $group;
    });

    $this->actingAs(admin())->get(route('admin.groups.show', $group->id))->assertOk()
        ->assertSee(['Tay 2 Hụi viên 2', '400.000 đ', 'Thiếu', 'Tay 1 · Hụi viên 1']);
});

test('a missing group id is a 404 for admins', function () {
    $this->actingAs(admin())->get(route('admin.groups.show', 'khong-co'))->assertNotFound();
});

test('the user list filters by name or email, role and locked state', function () {
    $this->actingAs(admin());
    User::factory()->create(['name' => 'Chị Lan', 'email' => 'lan@example.com']);
    User::factory()->host()->create(['name' => 'Cô Hoa', 'email' => 'hoa@example.com']);
    User::factory()->locked()->create(['name' => 'Anh Tú', 'email' => 'tu@example.com']);

    Livewire::test(UserIndex::class)
        ->set('search', 'lan@')->assertSee('Chị Lan')->assertDontSee('Cô Hoa')
        ->set('search', '')->set('role', 'chu_hui')->assertSee('Cô Hoa')->assertDontSee('Chị Lan')
        ->set('role', '')->set('lockedOnly', true)->assertSee('Anh Tú')->assertDontSee('Chị Lan');
});

test('a locked account is signed out and cannot sign in again until unlocked', function () {
    $user = User::factory()->create(['email' => 'lan@example.com']);
    $this->actingAs($admin = admin());
    Livewire::test(UserShow::class, ['user' => $user])->call('lock')->assertSet('actionError', null);

    expect($user->fresh()->isLocked())->toBeTrue()
        ->and(AdminLog::query()->where('action', AdminAction::Lock)->sole()->user_id)->toBe($user->id);

    $this->actingAs($user->fresh())->get(route('dashboard'))
        ->assertRedirect(route('login'))->assertSessionHas('status', User::LOCKED_MESSAGE);
    $this->assertGuest();

    Volt::test('pages.auth.login')->set('form.email', 'lan@example.com')->set('form.password', 'password')
        ->call('login')->assertHasErrors('form.email');
    $this->assertGuest();

    $this->actingAs($admin);
    Livewire::test(UserShow::class, ['user' => $user])->call('unlock');
    Auth::logout();

    Volt::test('pages.auth.login')->set('form.email', 'lan@example.com')->set('form.password', 'password')
        ->call('login')->assertHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

test('admins cannot be locked', function () {
    config(['hui.admin_emails' => ['admin@example.com', 'second@example.com']]);
    $this->actingAs(admin());
    $otherAdmin = User::factory()->create(['email' => 'second@example.com']);

    Livewire::test(UserShow::class, ['user' => $otherAdmin])->call('lock')
        ->assertSet('actionError', 'Không khóa được tài khoản quản trị viên.');

    expect($otherAdmin->fresh()->isLocked())->toBeFalse();
});

test('admins send a password reset email without ever setting the password', function () {
    Notification::fake();
    $user = User::factory()->create();
    $passwordHash = $user->password;
    $this->actingAs(admin());

    Livewire::test(UserShow::class, ['user' => $user])->call('sendPasswordReset')->assertSet('actionError', null);

    Notification::assertSentTo($user, ResetPassword::class);
    expect($user->fresh()->password)->toBe($passwordHash)
        ->and(AdminLog::query()->where('action', AdminAction::SendPasswordReset)->count())->toBe(1);
});

test('admins change the role only while the account has no groups', function () {
    $user = User::factory()->create();
    $this->actingAs($admin = admin());

    Livewire::test(UserShow::class, ['user' => $user])->call('changeRole', 'chu_hui')->assertSet('actionError', null);
    expect($user->fresh()->role)->toBe(UserRole::ChuHui)
        ->and(AdminLog::query()->where('action', AdminAction::ChangeRole)->sole()->meta)->toEqual(['from' => 'hui_vien', 'to' => 'chu_hui']);

    groupOwnedBy($user->fresh(), fn () => createHostGroup(['total_slots' => 2]));
    $this->actingAs($admin);

    Livewire::test(UserShow::class, ['user' => $user->fresh()])->call('changeRole', 'hui_vien')
        ->assertSet('actionError', 'Chỉ đổi được vai trò khi tài khoản chưa có dây hụi nào.');
    expect($user->fresh()->role)->toBe(UserRole::ChuHui)
        ->and(AdminLog::query()->where('action', AdminAction::ChangeRole)->count())->toBe(1);
});

test('the overview counts accounts, signups per day, active users and groups', function () {
    $this->travelTo('2026-10-23 10:00:00');
    $member = User::factory()->create(['created_at' => '2026-10-23 08:00:00', 'last_seen_at' => now()]);
    User::factory()->host()->locked()->create(['created_at' => '2026-10-20 23:59:00', 'last_seen_at' => '2026-10-10 09:00:00']);
    User::factory()->create(['created_at' => '2026-09-01 08:00:00']);
    groupOwnedBy($member, fn () => createHuiGroup());

    $stats = app(AdminStats::class)->overview(now()->toDateTimeImmutable());

    expect($stats['users'])->toBe(['total' => 3, 'hui_vien' => 2, 'chu_hui' => 1, 'locked' => 1])
        ->and($stats['signups'])->toMatchArray(['today' => 1, 'week' => 2, 'month' => 2])
        ->and($stats['signups']['daily'])->toHaveCount(AdminStats::SIGNUP_DAYS)
        ->and(array_slice($stats['signups']['daily'], -4))->toBe([
            ['date' => '2026-10-20', 'count' => 1],
            ['date' => '2026-10-21', 'count' => 0],
            ['date' => '2026-10-22', 'count' => 0],
            ['date' => '2026-10-23', 'count' => 1],
        ])
        ->and($stats['active'])->toBe(['week' => 1, 'month' => 2])
        ->and($stats['groups'])->toBe(['total' => 1, 'byStatus' => ['dang_chay' => 1, 'ket_thuc' => 0, 'be' => 0], 'byRole' => ['hui_vien' => 1, 'chu_hui' => 0]]);
});

test('last seen is recorded at most every 15 minutes', function () {
    $this->travelTo('2026-10-23 10:00:00');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings'))->assertOk();
    expect($user->fresh()->last_seen_at->format('H:i'))->toBe('10:00');

    $this->travelTo('2026-10-23 10:10:00');
    $this->actingAs($user->fresh())->get(route('settings'));
    expect($user->fresh()->last_seen_at->format('H:i'))->toBe('10:00');

    $this->travelTo('2026-10-23 10:16:00');
    $this->actingAs($user->fresh())->get(route('settings'));
    expect($user->fresh()->last_seen_at->format('H:i'))->toBe('10:16');
});
