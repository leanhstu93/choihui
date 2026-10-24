<?php

declare(strict_types=1);

return [
    /*
     * Khóa PIN (FR-13): số phút không thao tác thì phải nhập lại PIN, và số lần
     * nhập sai tối đa trước khi bị đăng xuất.
     */
    'pin_idle_minutes' => (int) env('HUI_PIN_IDLE_MINUTES', 5),
    'pin_max_attempts' => 5,

    /*
     * Email quản trị viên, cách nhau bằng dấu phẩy (document/05-quan-tri.md Q3).
     * Chỉ ghi email đã có tài khoản: app không bắt xác minh email.
     */
    'admin_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),
];
