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
    /** Hết dung lượng TURN miễn phí của tháng: chỉ còn kết nối trực tiếp. */
    public const MONTHLY_LIMIT_MESSAGE = 'Máy chủ trung chuyển đã hết dung lượng miễn phí của tháng này, nên chỉ gửi được khi hai máy kết nối thẳng với nhau. Nếu không kết nối được, hãy cho hai máy dùng chung một wifi.';

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

    public static function turnDisabled(): self
    {
        return new self('turn_disabled', 'Mạng đang chặn kết nối thẳng giữa hai máy. Hãy cho hai máy dùng chung một wifi rồi thử lại.', 503);
    }

    public static function turnDailyLimit(): self
    {
        return new self('turn_daily_limit', 'Hôm nay bạn đã dùng hết lượt đi qua máy chủ trung chuyển. Hãy cho hai máy dùng chung một wifi để gửi trực tiếp (không giới hạn), hoặc thử lại vào ngày mai.', 429);
    }

    public static function turnMonthlyLimit(): self
    {
        return new self('turn_monthly_limit', self::MONTHLY_LIMIT_MESSAGE, 429);
    }

    public static function nearbyGone(): self
    {
        return new self('nearby_gone', 'Máy đó không còn mở trang hoặc đã kết nối với máy khác. Hãy thử lại, hoặc dùng mã phòng / mã QR.', 404);
    }
}
