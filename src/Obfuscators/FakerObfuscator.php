<?php

declare(strict_types=1);

namespace Intermax\Blur\Obfuscators;

use Intermax\Blur\Contracts\Obfuscator;
use InvalidArgumentException;

class FakerObfuscator implements Obfuscator
{
    /**
     * @param array<int, mixed>|null $parameters
     */
    public function generate(array|null $parameters = null): mixed
    {
        if ($parameters === null) {
            throw new InvalidArgumentException('No faker method provided');
        }

        return fake()->{$parameters[0]}();
    }
}
