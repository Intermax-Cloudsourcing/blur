<?php

declare(strict_types=1);

namespace Intermax\Blur\Obfuscators;

use Intermax\Blur\Contracts\Obfuscator;
use InvalidArgumentException;

class FixedStringObfuscator implements Obfuscator
{
    /**
     * @param array<int, mixed>|null $parameters
     */
    public function generate(array|null $parameters = null): mixed
    {
        if ($parameters === null) {
            throw new InvalidArgumentException('No fixed value provided.');
        }

        return $parameters[0];
    }
}
