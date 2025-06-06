<?php

declare(strict_types=1);

namespace Intermax\Blur\Obfuscators;

use Faker;
use Faker\Generator;
use Intermax\Blur\Contracts\Obfuscator;
use InvalidArgumentException;

class FakerObfuscator implements Obfuscator
{
    private static ?Generator $faker = null;

    public function __construct()
    {
        if (self::$faker === null) {
            self::refreshFakerInstance();
        }
    }

    /**
     * @param  array<int, mixed>|null  $parameters
     */
    public function generate(?array $parameters = null): mixed
    {
        if ($parameters === null) {
            throw new InvalidArgumentException('No faker method provided');
        }

        return self::$faker->{$parameters[0]}();
    }

    public static function refreshFakerInstance(): void
    {
        self::$faker = Faker\Factory::create();
    }
}
