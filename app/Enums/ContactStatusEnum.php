<?php

namespace App\Enums;

enum ContactStatusEnum: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case BLOCKED = 'blocked';
    case CANCELLED = 'cancelled';
    case REMOVED = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::BLOCKED => 'Blocked',
            self::CANCELLED => 'Cancelled',
            self::REMOVED => 'Removed',
        };
    }
}
