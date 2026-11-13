<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi khi dùng phòng gửi file. $reason là mã máy đọc cho trình duyệt; message là câu tiếng Việt
 * nói rõ người dùng nên làm gì.
 */
final class TransferRoomException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('not_found', 'Không tìm thấy phòng với mã này. Kiểm tra lại mã trên máy kia.', 404);
    }

    public static function expired(): self
    {
        return new self('expired', 'Mã phòng đã hết hạn. Trên máy gửi, bấm "Tạo mã mới" rồi nhập lại.', 410);
    }

    public static function full(): self
    {
        return new self('full', 'Phòng này đã có đủ 2 thiết bị. Nhờ máy gửi tạo mã mới.', 409);
    }

    public static function forbidden(): self
    {
        return new self('forbidden', 'Thiết bị này không thuộc phòng. Tải lại trang và vào phòng lại.', 403);
    }

    public static function tooManySignals(): self
    {
        return new self('too_many_signals', 'Kết nối đang bị nghẽn. Tải lại trang trên cả hai máy rồi thử lại.', 429);
    }

    public static function tooManyAttempts(int $seconds): self
    {
        return new self('too_many_attempts', 'Bạn nhập sai mã nhiều lần. Thử lại sau '.max(1, (int) ceil($seconds / 60)).' phút, hoặc quét mã QR trên máy gửi.', 429);
    }
}
