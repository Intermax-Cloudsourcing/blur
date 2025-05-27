<?php

declare(strict_types=1);

namespace Intermax\Blur\Obfuscators;

use Faker\Generator;
use Intermax\Blur\Contracts\Obfuscator;
use InvalidArgumentException;

class FakerObfuscator implements Obfuscator
{
    private Generator $faker;

    public function __construct()
    {
        // Create a single Faker instance to reuse
        $this->faker = fake();
    }

    /**
     * @param  array<int, mixed>|null  $parameters
     */
    public function generate(?array $parameters = null): mixed
    {
        if ($parameters === null) {
            throw new InvalidArgumentException('No faker method provided');
        }

        return $this->faker->{$parameters[0]}();
    }
}
