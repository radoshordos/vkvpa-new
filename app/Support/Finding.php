<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Severity;

final readonly class Finding
{
    public function __construct(
        public Severity $severity,
        public string $message,
    ) {}
}
