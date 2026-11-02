<?php

namespace App\Enums;

enum Department: string
{
    case Digital = 'digital';
    case Classic = 'classic';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'دیجیتال',
            self::Classic => 'کلاسیک',
            self::Online => 'آنلاین',
        };
    }

    public static function fromPersian(?string $value): ?self
    {
        $v = trim((string) $value);

        return match ($v) {
            'دیجیتال' => self::Digital,
            'کلاسیک' => self::Classic,
            'آنلاین' => self::Online,
            default => null,
        };
    }
}
