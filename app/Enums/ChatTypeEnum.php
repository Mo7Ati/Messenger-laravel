<?php

namespace App\Enums;

enum ChatTypeEnum: string
{
    case PEER = 'peer';
    case GROUP = 'group';

    public function label(): string
    {
        return match ($this) {
            self::PEER => 'Peer',
            self::GROUP => 'Group',
        };
    }
}
