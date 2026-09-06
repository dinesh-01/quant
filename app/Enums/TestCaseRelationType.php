<?php

namespace App\Enums;

/**
 * How two test cases relate to each other.
 *
 * Related is symmetric: one row is enough, and the reverse is the same
 * sentence. Depends-on and blocks are directional — the destination is the
 * other case the source refers to.
 */
enum TestCaseRelationType: string
{
    case Related = 'related';
    case DependsOn = 'depends_on';
    case Blocks = 'blocks';

    public function isSymmetric(): bool
    {
        return $this === self::Related;
    }

    public function outgoingLabel(): string
    {
        return match ($this) {
            self::Related => 'is related to',
            self::DependsOn => 'depends on',
            self::Blocks => 'blocks',
        };
    }

    public function incomingLabel(): string
    {
        return match ($this) {
            self::Related => 'is related to',
            self::DependsOn => 'is a dependency of',
            self::Blocks => 'is blocked by',
        };
    }
}
