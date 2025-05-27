<?php

declare(strict_types=1);

namespace Intermax\Blur\Contracts;

interface Obfuscator
{
    /**
     * @param  array<int, mixed>|null  $parameters
     */
    public function generate(?array $parameters = null): mixed;
}
