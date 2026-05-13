<?php

namespace Idei\Usim\Enums;

enum SelectionMode: string
{
	case NONE = 'none';
	case SINGLE = 'single';
	case MULTIPLE = 'multiple';

	public static function isValid(?string $value): bool
	{
        if ($value === null) {
            return false;
        }
        $value = strtolower(trim((string)$value));
		return self::tryFrom($value) !== null;
	}
}

