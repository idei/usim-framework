<?php

namespace Idei\Usim\Enums;

/**
 * Time Unit Enum
 *
 * Defines time units for timeout dialogs
 */
enum TimeUnit: string
{
    case SECONDS = 'seconds';
    case MINUTES = 'minutes';
    case HOURS = 'hours';
    case DAYS = 'days';

    /**
     * Get singular label for this time unit
     */
    public function getSingularLabel(): string
    {
        return match($this) {
            self::SECONDS => t('usim.time_unit.second.singular'),
            self::MINUTES => t('usim.time_unit.minute.singular'),
            self::HOURS => t('usim.time_unit.hour.singular'),
            self::DAYS => t('usim.time_unit.day.singular'),
        };
    }

    /**
     * Get plural label for this time unit
     */
    public function getPluralLabel(): string
    {
        return match($this) {
            self::SECONDS => t('usim.time_unit.second.plural'),
            self::MINUTES => t('usim.time_unit.minute.plural'),
            self::HOURS => t('usim.time_unit.hour.plural'),
            self::DAYS => t('usim.time_unit.day.plural'),
        };
    }

    /**
     * Get label based on quantity (singular/plural)
     */
    public function getLabel(int $quantity): string
    {
        return $quantity === 1 ? $this->getSingularLabel() : $this->getPluralLabel();
    }

    /**
     * Convert value to milliseconds for JavaScript timer
     */
    public function toMilliseconds(int $value): int
    {
        return match($this) {
            self::SECONDS => $value * 1000,
            self::MINUTES => $value * 60 * 1000,
            self::HOURS => $value * 60 * 60 * 1000,
            self::DAYS => $value * 24 * 60 * 60 * 1000,
        };
    }
}
