<?php

declare(strict_types=1);

/*
 * Quy tắc phụ thuộc giữa các tầng (document/02-kien-truc.md mục 4).
 */

arch('domain is framework-free')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\Models', 'App\Actions', 'App\Livewire', 'App\Http']);

arch('actions do not depend on the UI layer')
    ->expect('App\Actions')
    ->not->toUse(['App\Livewire', 'App\Http']);

arch('models do not depend on actions or the UI layer')
    ->expect('App\Models')
    ->not->toUse(['App\Actions', 'App\Livewire', 'App\Http']);

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('all source files declare strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch()->preset()->php();

arch()->preset()->security();
