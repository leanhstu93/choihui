<?php

declare(strict_types=1);

use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\GoldPhotoController;
use App\Http\Controllers\HealthPhotoController;
use App\Http\Controllers\LunarCalendarController;
use App\Http\Controllers\RepairPhotoController;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\GroupShow as AdminGroupShow;
use App\Livewire\Admin\HolidayIndex as AdminHolidayIndex;
use App\Livewire\Admin\LogIndex as AdminLogIndex;
use App\Livewire\Admin\UserIndex as AdminUserIndex;
use App\Livewire\Admin\UserShow as AdminUserShow;
use App\Livewire\Dashboard;
use App\Livewire\Family\EventForm as FamilyEventForm;
use App\Livewire\Family\EventIndex as FamilyEventIndex;
use App\Livewire\Family\HealthIndex;
use App\Livewire\Family\PersonForm as HealthPersonForm;
use App\Livewire\Family\PersonShow as HealthPersonShow;
use App\Livewire\Family\RecordForm as HealthRecordForm;
use App\Livewire\Gold\GoldIndex;
use App\Livewire\Gold\GoldItemForm;
use App\Livewire\Gold\GoldItemShow;
use App\Livewire\Gold\GoldPrices;
use App\Livewire\Groups\GroupForm;
use App\Livewire\Groups\GroupIndex;
use App\Livewire\Groups\GroupShow;
use App\Livewire\Groups\GroupStatement;
use App\Livewire\Host\Dashboard as HostDashboard;
use App\Livewire\Host\GroupForm as HostGroupForm;
use App\Livewire\Host\GroupIndex as HostGroupIndex;
use App\Livewire\Host\GroupShow as HostGroupShow;
use App\Livewire\Host\ReportPage as HostReportPage;
use App\Livewire\Host\RoundShow as HostRoundShow;
use App\Livewire\Members\MemberIndex;
use App\Livewire\Members\MemberShow;
use App\Livewire\NetWorth\NetWorthPage;
use App\Livewire\Repairs\RepairIndex;
use App\Livewire\Repairs\RepairItemForm;
use App\Livewire\Repairs\RepairItemShow;
use App\Livewire\Repairs\RepairRecordForm;
use App\Livewire\Reports\ProfitReportPage;
use App\Livewire\Rounds\RoundShow;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\Settings\UnlockPage;
use App\Queries\LunarDateLookup;
use App\Queries\YearCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

// Trang giới thiệu cho khách; người đã đăng nhập vào thẳng sổ của mình.
Route::get('/', fn (LunarDateLookup $lunar, YearCalendar $calendar) => auth()->check()
    ? redirect()->route('net-worth')
    : view('welcome', ['lunarLookup' => $lunar->from(CarbonImmutable::today()), 'calendar' => $calendar->forYear(CarbonImmutable::today()->year)]))->name('home');

// Gửi file trực tiếp giữa hai thiết bị (không cần đăng nhập); ?ma= là mã phòng từ QR.
Route::view('gui-file', 'transfer.show')->name('transfer.show');
// "Chia sẻ" từ app khác (PWA share target). Service worker xử lý trước; tới được đây nghĩa là
// service worker chưa chạy, file không giữ lại được: quay về trang và báo cho người dùng.
Route::post('gui-file/chia-se', fn () => redirect()->route('transfer.show', ['chia-se' => 'loi'], 303))->name('transfer.share');

// Lịch âm công khai (khối lịch trên trang giới thiệu và các trang /lich-am cho Google).
Route::controller(LunarCalendarController::class)->name('lunar.')->group(function () {
    Route::get('lich/{year}.json', 'data')->whereNumber('year')->name('data');
    Route::get('lich/{year}.ics', 'ics')->whereNumber('year')->name('ics');
    Route::get('lich-am', 'today')->name('today');
    Route::get('lich-am/{year}', 'year')->where('year', '[0-9]{4}')->name('year');
    Route::get('lich-am/{year}/{month}', 'month')->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}'])->name('month');
    Route::get('lich-am/{date}', 'day')->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('day');
});

// SEO: sinh theo APP_URL để đổi tên miền không phải sửa file.
Route::get('robots.txt', fn () => response("User-agent: *\nDisallow: /chu-hui\nDisallow: /dashboard\nDisallow: /tong-tai-san\nDisallow: /vang\nDisallow: /sua-chua\nDisallow: /gia-dinh\nDisallow: /quan-tri\nDisallow: /lich/\nSitemap: ".url('sitemap.xml')."\n")
    ->header('Content-Type', 'text/plain; charset=UTF-8'))->name('robots');
Route::view('chinh-sach', 'privacy')->name('privacy');
Route::get('sitemap.xml', fn () => response()->view('sitemap', ['year' => CarbonImmutable::today()->year])->header('Content-Type', 'application/xml; charset=UTF-8'))->name('sitemap');

Route::middleware('auth')->group(function () {
    Route::get('unlock', UnlockPage::class)->name('unlock');

    Route::middleware('pin')->group(function () {
        Route::get('tong-tai-san', NetWorthPage::class)->middleware(['verified', 'area:tong'])->name('net-worth');
        Route::get('dashboard', Dashboard::class)->middleware(['verified', 'area:hui'])->name('dashboard');
        Route::view('profile', 'profile')->name('profile');

        Route::middleware(['role:hui_vien', 'area:hui'])->group(function () {
            Route::get('groups', GroupIndex::class)->name('groups.index');
            Route::get('groups/create', GroupForm::class)->name('groups.create');
            Route::get('groups/{group}', GroupShow::class)->name('groups.show');
            Route::get('groups/{group}/edit', GroupForm::class)->name('groups.edit');
            Route::get('groups/{group}/statement', GroupStatement::class)->name('groups.statement');
            Route::get('rounds/{round}', RoundShow::class)->name('rounds.show');

            Route::get('reports', ProfitReportPage::class)->name('reports');
        });

        Route::middleware(['role:chu_hui', 'area:hui'])->prefix('chu-hui')->name('host.')->group(function () {
            Route::get('/', HostDashboard::class)->name('dashboard');
            Route::get('reports', HostReportPage::class)->name('reports');
            Route::get('groups', HostGroupIndex::class)->name('groups.index');
            Route::get('groups/create', HostGroupForm::class)->name('groups.create');
            Route::get('groups/{group}', HostGroupShow::class)->name('groups.show');
            Route::get('groups/{group}/edit', HostGroupForm::class)->name('groups.edit');
            Route::get('rounds/{round}', HostRoundShow::class)->name('rounds.show');

            Route::get('members', MemberIndex::class)->name('members.index');
            Route::get('members/{member}', MemberShow::class)->name('members.show');
        });

        // Trang quản trị (document/05-quan-tri.md): chỉ email trong ADMIN_EMAILS, còn lại 404.
        Route::middleware('admin')->prefix('quan-tri')->name('admin.')->group(function () {
            Route::get('/', AdminDashboard::class)->name('dashboard');
            Route::get('users', AdminUserIndex::class)->name('users.index');
            Route::get('users/{user}', AdminUserShow::class)->name('users.show');
            Route::get('groups/{groupId}', AdminGroupShow::class)->name('groups.show');
            Route::get('logs', AdminLogIndex::class)->name('logs');
            Route::get('nghi-le', AdminHolidayIndex::class)->name('holidays');
        });

        // Sổ vàng (document/06-vang.md): chung cho cả hai vai trò.
        Route::middleware('area:vang')->prefix('vang')->name('gold.')->group(function () {
            Route::get('/', GoldIndex::class)->name('index');
            Route::get('gia', GoldPrices::class)->name('prices');
            Route::get('them', GoldItemForm::class)->name('create');
            Route::get('anh/{goldItemPhoto}', GoldPhotoController::class)->name('photos.show');
            Route::get('{goldItem}', GoldItemShow::class)->name('show');
            Route::get('{goldItem}/sua', GoldItemForm::class)->name('edit');
        });

        // Sổ sửa chữa (document/08-sua-chua.md): chung cho cả hai vai trò.
        Route::middleware('area:sua')->prefix('sua-chua')->name('repairs.')->group(function () {
            Route::get('/', RepairIndex::class)->name('index');
            Route::get('them', RepairItemForm::class)->name('items.create');
            Route::get('anh/{repairRecordPhoto}', RepairPhotoController::class)->name('photos.show');
            Route::get('lan-sua/{repairRecord}/sua', RepairRecordForm::class)->name('records.edit');
            Route::get('{repairItem}', RepairItemShow::class)->name('show');
            Route::get('{repairItem}/sua', RepairItemForm::class)->name('edit');
            Route::get('{repairItem}/them-lan-sua', RepairRecordForm::class)->name('records.create');
        });

        // Sổ gia đình: ngày nhớ và sức khỏe (document/09-gia-dinh.md): chung cho cả hai vai trò.
        Route::middleware('area:nha')->prefix('gia-dinh')->name('family.')->group(function () {
            Route::get('/', FamilyEventIndex::class)->name('index');
            Route::get('them', FamilyEventForm::class)->name('events.create');
            Route::get('ngay-nho/{familyEvent}/sua', FamilyEventForm::class)->name('events.edit');

            Route::prefix('suc-khoe')->name('health.')->group(function () {
                Route::get('/', HealthIndex::class)->name('index');
                Route::get('them', HealthPersonForm::class)->name('people.create');
                Route::get('anh/{healthRecordPhoto}', HealthPhotoController::class)->name('photos.show');
                Route::get('lan-kham/{healthRecord}/sua', HealthRecordForm::class)->name('records.edit');
                Route::get('{healthPerson}', HealthPersonShow::class)->name('show');
                Route::get('{healthPerson}/sua', HealthPersonForm::class)->name('people.edit');
                Route::get('{healthPerson}/them-lan-kham', HealthRecordForm::class)->name('records.create');
            });
        });

        Route::get('settings', SettingsPage::class)->name('settings');
        Route::get('settings/backup', BackupDownloadController::class)->middleware('role:hui_vien')->name('settings.backup');
    });
});

require __DIR__.'/auth.php';
