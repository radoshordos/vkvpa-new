<?php

declare(strict_types=1);

namespace App\Enums;

enum Severity: string
{
    case Fatal = 'fatal';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Fatal => 'Fatal',
            self::Warning => 'Varování',
            self::Info => 'Info',
        };
    }

    /** Tailwind CSS classes for the badge pill. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Fatal => 'bg-red-100 text-red-800 border border-red-300',
            self::Warning => 'bg-amber-100 text-amber-800 border border-amber-300',
            self::Info => 'bg-blue-100 text-blue-800 border border-blue-300',
        };
    }

    /** Tailwind CSS classes for the icon/bullet. */
    public function iconClasses(): string
    {
        return match ($this) {
            self::Fatal => 'text-red-500',
            self::Warning => 'text-amber-500',
            self::Info => 'text-blue-400',
        };
    }
}
