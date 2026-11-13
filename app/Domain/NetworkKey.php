<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Khóa "cùng mạng" cho tính năng tự thấy máy gần: các máy sau cùng một router ra Internet bằng
 * cùng IPv4 công cộng, hoặc cùng tiền tố /64 với IPv6. Chỉ lưu bản băm có muối, không lưu IP. PHP thuần.
 */
final class NetworkKey
{
    public static function of(string $ip, string $secret): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return hash('sha256', 'v4|'.$ip.'|'.$secret);
        }

        $packed = @inet_pton($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false || $packed === false) {
            return null;
        }

        // IPv4 nằm trong IPv6 (::ffff:1.2.3.4): coi như IPv4.
        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return self::of((string) inet_ntop(substr($packed, 12)), $secret);
        }

        return hash('sha256', 'v6|'.bin2hex(substr($packed, 0, 8)).'|'.$secret);
    }
}
