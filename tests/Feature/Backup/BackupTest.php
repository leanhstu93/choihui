<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\WinnerType;
use App\Exceptions\HuiActionException;
use App\Livewire\Settings\SettingsPage;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use App\Queries\UserBackup;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Dữ liệu mẫu đủ các trường hợp: tay hốt, đóng thiếu, còn nợ, dây bể.
 */
function seedBackupData(): void
{
    $group = createHuiGroup(['name' => 'Hụi cô Hoa', 'commission_amount' => 200_000], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::NguoiKhac, winnerName: 'Chủ hụi');
    app(RecordRoundResult::class)->handle($group->rounds[1], 150_000, WinnerType::Minh, $group->slots[1]);
    app(MarkPaid::class)->handle($group->rounds[0]->cashEntries()->whereBelongsTo($group->slots[0], 'slot')->sole(), 500_000, new DateTimeImmutable('2026-01-06'), 'Đóng trước một nửa');
    app(RecordReceipt::class)->handle($group->rounds[1]->cashEntries()->whereBelongsTo($group->slots[1], 'slot')->sole(), 8_000_000, new DateTimeImmutable('2026-02-05'));

    app(MarkGroupBroken::class)->handle(createHuiGroup(['name' => 'Hụi bể'], ['Tay A']), 4);
}

/**
 * Bản sao lưu bỏ các trường thay đổi giữa hai lần xuất (thời điểm, id) để so sánh nội dung.
 *
 * @param  array<string, mixed>  $backup
 * @return array<string, mixed>
 */
function comparableBackup(array $backup): array
{
    unset($backup['exported_at']);

    foreach ($backup['groups'] as &$group) {
        $labels = array_column($group['slots'], 'label', 'key');
        $group['slots'] = array_values($labels);

        foreach ($group['rounds'] as &$round) {
            $round['winner_slot'] = $round['winner_slot'] === null ? null : $labels[$round['winner_slot']];
            foreach ($round['entries'] as &$entry) {
                $entry['slot'] = $labels[$entry['slot']];
            }
            usort($round['entries'], fn ($a, $b) => $a['slot'] <=> $b['slot']);
        }
    }

    return $backup;
}

test('a backup restored into another account reproduces the same data', function () {
    $this->actingAs($alice = User::factory()->create(['reminder_days_before' => 5]));
    seedBackupData();
    $backup = app(UserBackup::class)->export($alice);

    $this->actingAs($bob = User::factory()->create());
    $stats = app(RestoreBackup::class)->handle($bob, json_decode(json_encode($backup), true));

    expect($stats)->toBe(['groups' => 2, 'rounds' => 20, 'entries' => 4])
        ->and(comparableBackup(app(UserBackup::class)->export($bob->refresh())))->toBe(comparableBackup($backup))
        ->and(HuiGroup::query()->withoutGlobalScopes()->where('user_id', $alice->id)->count())->toBe(2);
});

test('restoring replaces the existing data', function () {
    $this->actingAs($user = User::factory()->create());
    createHuiGroup(['name' => 'Dây cũ']);
    $backup = app(UserBackup::class)->export($user);
    createHuiGroup(['name' => 'Dây mới tạo sau khi sao lưu']);

    app(RestoreBackup::class)->handle($user, $backup);

    expect(HuiGroup::pluck('name')->all())->toBe(['Dây cũ']);
});

test('an invalid backup is rejected and the existing data is kept', function (Closure $corrupt, string $message) {
    $this->actingAs($user = User::factory()->create());
    seedBackupData();
    $backup = app(UserBackup::class)->export($user);
    $counts = [HuiGroup::count(), MySlot::count(), HuiRound::count(), CashEntry::count()];

    expect(fn () => app(RestoreBackup::class)->handle($user, $corrupt($backup)))->toThrow(HuiActionException::class, $message)
        ->and([HuiGroup::count(), MySlot::count(), HuiRound::count(), CashEntry::count()])->toBe($counts);
})->with([
    'not a backup' => [fn ($backup) => ['hello' => 'world'], 'File sao lưu không hợp lệ'],
    'unknown version' => [fn ($backup) => array_merge($backup, ['version' => 99]), 'File sao lưu không hợp lệ'],
    'missing round' => [function ($backup) {
        array_pop($backup['groups'][0]['rounds']);

        return $backup;
    }, 'danh sách kỳ phải đủ từ 1 đến 10'],
    'winner slot not in group' => [function ($backup) {
        $backup['groups'][0]['rounds'][1]['winner_slot'] = 'khong-ton-tai';

        return $backup;
    }, 'người hốt kỳ 2 không hợp lệ'],
    'slot wins twice' => [function ($backup) {
        $backup['groups'][0]['rounds'][2]['winner_type'] = 'minh';
        $backup['groups'][0]['rounds'][2]['winner_slot'] = $backup['groups'][0]['rounds'][1]['winner_slot'];
        $backup['groups'][0]['rounds'][2]['bid_amount'] = 0;

        return $backup;
    }, 'một tay hốt nhiều hơn một lần'],
    'bid above face value' => [function ($backup) {
        $backup['groups'][0]['rounds'][1]['bid_amount'] = 2_000_000;

        return $backup;
    }, 'lãi thăm kỳ 2 lớn hơn mệnh giá'],
    'negative amount' => [function ($backup) {
        $backup['groups'][0]['rounds'][0]['entries'][0]['amount_actual'] = -1;

        return $backup;
    }, 'File sao lưu không hợp lệ'],
]);

test('a backup cannot be restored into another signed-in user\'s account', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => app(RestoreBackup::class)->handle(User::factory()->create(), ['app' => 'so-hui', 'version' => 1, 'groups' => []]))
        ->toThrow(HuiActionException::class, 'Chỉ được khôi phục vào tài khoản đang đăng nhập.');
});

test('the backup download only contains the signed-in user\'s data', function () {
    $this->actingAs(User::factory()->create());
    createHuiGroup(['name' => 'Dây của người khác']);

    $this->actingAs(User::factory()->create());
    createHuiGroup(['name' => 'Dây của tôi']);

    $this->get(route('settings.backup'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="so-hui-sao-luu-'.now()->format('Y-m-d').'.json"')
        ->assertJsonPath('app', 'so-hui')
        ->assertJsonCount(1, 'groups')
        ->assertJsonPath('groups.0.name', 'Dây của tôi');
});

test('guests cannot download a backup', function () {
    $this->get(route('settings.backup'))->assertRedirect(route('login'));
});

test('a backup file can be restored from the settings page', function () {
    $this->actingAs($user = User::factory()->create());
    createHuiGroup(['name' => 'Dây trong file']);
    $json = json_encode(app(UserBackup::class)->export($user));
    createHuiGroup(['name' => 'Dây sẽ bị thay']);

    Livewire::test(SettingsPage::class)
        ->set('backupFile', UploadedFile::fake()->createWithContent('so-hui.json', $json))
        ->call('restoreBackup')
        ->assertHasErrors(['confirmRestore' => 'Vui lòng xác nhận thay thế dữ liệu hiện tại.'])
        ->set('confirmRestore', true)
        ->call('restoreBackup')
        ->assertHasNoErrors();

    expect(HuiGroup::pluck('name')->all())->toBe(['Dây trong file']);
});

test('a file that is not JSON shows an error on the settings page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SettingsPage::class)
        ->set('backupFile', UploadedFile::fake()->createWithContent('anh.json', 'không phải json'))
        ->set('confirmRestore', true)
        ->call('restoreBackup')
        ->assertHasErrors(['backupFile' => 'File không phải file sao lưu JSON của Sổ hụi.']);
});
