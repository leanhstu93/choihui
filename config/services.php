<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google Analytics 4 (mã công khai, nằm sẵn trong HTML). Chỉ bật mặc định ở production
    // để local / test không gửi số liệu ảo; GA_MEASUREMENT_ID (khác rỗng) ghi đè nếu cần.
    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID') ?: (env('APP_ENV') === 'production' ? 'G-N77C3ZFV99' : null),
    ],

    // Gửi file trực tiếp (/gui-file): máy chủ STUN công cộng giúp hai máy tìm đường nối thẳng.
    'transfer' => [
        'stun_urls' => array_values(array_filter(explode(',', (string) env('TRANSFER_STUN_URLS', 'stun:stun.cloudflare.com:3478,stun:stun.l.google.com:19302')))),

        // Máy chủ trung chuyển (Open Relay của Metered, gói miễn phí không cần thẻ) khi mạng chặn
        // kết nối thẳng. METERED_APP là tên miền con (<app>.metered.live). Để trống là tắt.
        'metered_app' => env('METERED_APP'),
        'metered_secret_key' => env('METERED_SECRET_KEY'),
        // Mật khẩu tạm thời: tạo sẵn (cần tới 2 phút để lan truyền), hết hạn cứng sau turn_credential_hours,
        // chỉ giao khi còn ít nhất turn_min_remaining_minutes để phiên gửi không bị cắt giữa chừng.
        'turn_credential_hours' => (int) env('TRANSFER_TURN_CREDENTIAL_HOURS', 6),
        'turn_min_remaining_minutes' => (int) env('TRANSFER_TURN_MIN_REMAINING_MINUTES', 120),
        'turn_propagation_seconds' => (int) env('TRANSFER_TURN_PROPAGATION_SECONDS', 120),
        'turn_pool_size' => (int) env('TRANSFER_TURN_POOL_SIZE', 2),
        // Mỗi lượt gửi qua trung chuyển tối đa ngần này; lớn hơn thì nhắc dùng chung wifi.
        'turn_max_bytes' => (int) env('TRANSFER_TURN_MAX_MB', 500) * 1024 ** 2,
        'turn_daily_per_ip' => (int) env('TRANSFER_TURN_DAILY_PER_IP', 5),
        // Hạn mức tháng (GB) dùng khi Metered không trả về quotaInGB.
        'turn_monthly_gb' => (float) env('TRANSFER_TURN_MONTHLY_GB', 20),
    ],

];
