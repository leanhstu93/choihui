<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

{{-- PWA: cài như app bằng "Thêm vào màn hình chính" (document/01-dac-ta.md mục 9) --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#047857">
<link rel="icon" type="image/png" href="/icons/favicon-48.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])

@if ($gaId = config('services.google_analytics.measurement_id'))
    {{-- Consent Mode v2: mặc định không đặt cookie, chỉ bật khi người dùng đồng ý (x-cookie-consent). --}}
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('consent', 'default', {
            analytics_storage: localStorage.getItem('cookie-consent') === 'granted' ? 'granted' : 'denied',
            ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
        });
        gtag('js', new Date());
        gtag('config', @js($gaId));
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
@endif
