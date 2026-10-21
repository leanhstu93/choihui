<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Models\Member;

/**
 * Thêm hoặc sửa hụi viên trong danh bạ của chủ hụi (CH-02).
 */
final class SaveMember
{
    /**
     * @param  array{name: string, phone?: string|null, note?: string|null}  $attributes
     */
    public function handle(?Member $member, array $attributes): Member
    {
        $member ??= new Member;
        $member->fill($attributes)->save();

        return $member;
    }
}
