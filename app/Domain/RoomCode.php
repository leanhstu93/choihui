<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Mã phòng gửi file: 6 ký tự từ bảng chữ không gây nhầm (bỏ 0, O, 1, I, L). PHP thuần.
 * 31^6 ≈ 887 triệu mã; cùng với giới hạn số lần nhập sai và hạn dùng ngắn thì đủ khó đoán.
 */
final class RoomCode
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Chuẩn hóa mã người dùng gõ: bỏ khoảng trắng, gạch, viết hoa; null nếu không hợp lệ.
     */
    public static function normalize(string $input): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-]+/', '', $input));

        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $code;
    }
}
