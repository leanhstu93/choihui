<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thao tác vi phạm quy tắc nghiệp vụ hụi. Thông báo bằng tiếng Việt để giao
 * diện hiện thẳng cho người dùng.
 */
class HuiActionException extends RuntimeException {}
