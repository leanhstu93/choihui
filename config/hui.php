<?php

declare(strict_types=1);

return [
    /*
     * Khóa PIN (FR-13): số phút không thao tác thì phải nhập lại PIN, và số lần
     * nhập sai tối đa trước khi bị đăng xuất.
     */
    'pin_idle_minutes' => (int) env('HUI_PIN_IDLE_MINUTES', 5),
    'pin_max_attempts' => 5,
];
