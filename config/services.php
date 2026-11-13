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

        // Máy chủ trung chuyển (TURN của Metered, gói miễn phí không cần thẻ) khi mạng chặn kết nối
        // thẳng: credential cố định trong .env, chỉ trả cho trình duyệt qua endpoint có giới hạn.
        // Để trống username/password là tắt.
        'turn_username' => env('METERED_TURN_USERNAME'),
        'turn_password' => env('METERED_TURN_PASSWORD'),
        'turn_urls' => array_values(array_filter(explode(',', (string) env('METERED_TURN_URLS', implode(',', [
            'stun:stun.relay.metered.ca:80',
            'turn:global.relay.metered.ca:80',
            'turn:global.relay.metered.ca:80?transport=tcp',
            'turn:global.relay.metered.ca:443',
            'turns:global.relay.metered.ca:443?transport=tcp',
        ]))))),
        // Không bắt buộc: tên app (<app>.metered.live) và khóa bí mật để đọc dung lượng đã dùng trong
        // tháng; có thì tự tắt trung chuyển khi sắp hết hạn mức.
        'metered_app' => env('METERED_APP'),
        'metered_secret_key' => env('METERED_SECRET_KEY'),
        // Mỗi lượt gửi qua trung chuyển tối đa ngần này; lớn hơn thì nhắc dùng chung wifi.
        'turn_max_bytes' => (int) env('TRANSFER_TURN_MAX_MB', 500) * 1024 ** 2,
        'turn_daily_per_ip' => (int) env('TRANSFER_TURN_DAILY_PER_IP', 5),
        // Hạn mức tháng (GB) dùng khi Metered không trả về quotaInGB.
        'turn_monthly_gb' => (float) env('TRANSFER_TURN_MONTHLY_GB', 20),
    ],

];
