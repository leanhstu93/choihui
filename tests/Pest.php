<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Actions\Host\CreateHostGroup;
use App\Models\HuiGroup;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Tạo một dây hụi qua Action thật cho người đang đăng nhập (mặc định N = 10, M = 1.000.000).
 *
 * @param  array<string, mixed>  $attributes
 * @param  list<string>  $slotLabels
 */
function createHuiGroup(array $attributes = [], array $slotLabels = ['Tay 1']): HuiGroup
{
    return app(CreateGroup::class)->handle(array_merge([
        'name' => 'Hụi 1tr cô Hoa',
        'face_value' => 1_000_000,
        'total_slots' => 10,
        'start_date' => '2026-01-05',
        'host_name' => 'Cô Hoa',
    ], $attributes), $slotLabels);
}

/**
 * Tạo một dây chủ hụi qua Action thật cho chủ hụi đang đăng nhập (mặc định N = 10,
 * M = 1.000.000). Mỗi tay một hụi viên mới tên "Hụi viên 1".."Hụi viên N".
 *
 * @param  array<string, mixed>  $attributes
 */
function createHostGroup(array $attributes = []): HuiGroup
{
    $attributes = array_merge([
        'name' => 'Hụi 1tr nhà mình',
        'face_value' => 1_000_000,
        'total_slots' => 10,
        'start_date' => '2026-01-05',
    ], $attributes);

    $memberIds = array_map(
        fn (int $slotNo): string => Member::create(['name' => "Hụi viên {$slotNo}"])->id,
        range(1, $attributes['total_slots']),
    );

    return app(CreateHostGroup::class)->handle($attributes, $memberIds);
}
