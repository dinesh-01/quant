<?php

namespace App\Enums;

/**
 * Listing lifecycle of a test plan, distinct from `is_open`.
 *
 * `is_open` still decides whether results can be recorded. This status is what
 * the plan index filters on: Active, Draft, or Archived.
 */
enum TestPlanStatus: string
{
    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Draft => 'Draft',
            self::Archived => 'Archived',
        };
    }

    /**
     * Archived plans stay out of the default listing, matching `is_active`.
     */
    public function isListed(): bool
    {
        return $this !== self::Archived;
    }
}
