<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Task;
use Carbon\CarbonImmutable;

/**
 * Một nhóm việc trên trang (document/10-viec-can-lam.md V3, V13). Tên nhóm do giao diện ghép
 * từ $key và $date.
 */
final readonly class TaskGroup
{
    public const string OVERDUE = 'overdue';

    public const string TODAY = 'today';

    public const string TOMORROW = 'tomorrow';

    public const string DAY = 'day';

    public const string LATER = 'later';

    public const string SOMEDAY = 'someday';

    public const string DONE_ON = 'done_on';

    /**
     * @param  list<Task>  $tasks
     */
    public function __construct(
        public string $key,
        public array $tasks,
        public ?CarbonImmutable $date = null,
    ) {}
}
