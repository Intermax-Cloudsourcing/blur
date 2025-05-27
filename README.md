# Blur

[![Latest Version on Packagist](https://img.shields.io/packagist/v/intermax/blur.svg?style=flat-square)](https://packagist.org/packages/intermax/blur)
[![Total Downloads](https://img.shields.io/packagist/dt/intermax/blur.svg?style=flat-square)](https://packagist.org/packages/intermax/blur)
[![License](https://img.shields.io/packagist/l/intermax/blur.svg?style=flat-square)](https://packagist.org/packages/intermax/blur)

Blur is a Laravel package that helps you obfuscate sensitive data in your database. It's perfect for creating anonymized copies of production databases for development and testing environments.

## Features

- 🔄 Obfuscate specific tables and columns in your database
- 🧩 Use Faker to generate realistic but fake data
- 🚀 Memory-optimized for handling large datasets
- 🔍 Interactive mode to select which tables to obfuscate
- 🛠️ Customizable obfuscation strategies
- 🔒 Safety checks to prevent running in production environments

## Installation

You can install the package via composer:

```bash
composer require intermax/blur
```

After installation, publish the configuration file:

```bash
php artisan vendor:publish --provider="Intermax\Blur\BlurServiceProvider"
```

## Configuration

After publishing the configuration, you can find the configuration file at `config/blur.php`. Here's an example configuration:

```php
<?php

declare(strict_types=1);

return [
    'tables' => [
        'users' => [
            'columns' => [
                'name' => 'faker:name',
                'email' => 'faker:email',
                // Add more columns as needed
            ],
            // 'chunk_size' => 2000, // Optional: Set a custom chunk size for processing
            // 'keys' => ['id'], // Optional: Specify when the automatic discovery won't work
            // 'method' => 'update', // Optional: Use 'clear' to truncate the table instead
        ],
        // Add more tables as needed
    ],
];
```

### Configuration Options

- **tables**: An array of tables to obfuscate
  - **columns**: (Optional, can be omitted when the table needs to be cleared) The columns to obfuscate and the obfuscation method to use. Only columns that should be obfuscated need to be specified.
  - **chunk_size**: (Optional) The number of records to process at once (default: 2000). See [Performance Considerations](#performance-considerations)
  - **keys**: (Optional) The key columns to use. The key columns are discovered when obfuscating, but if that fails (for example when there are no primary keys) the unique 'key' can be specified.
  - **method**: (Optional) The method to use for obfuscation (default: 'update', alternative: 'clear' to clear the table. This can be useful for tables like `jobs` or tables that store audit logs.)

## Usage

To obfuscate your database, run the following command:

```bash
php artisan blur:obfuscate
```

### Interactive Mode

You can use the interactive mode to select which tables to obfuscate:

```bash
php artisan blur:obfuscate --interactive
# or
php artisan blur:obfuscate -i
```

This will display a list of configured tables and allow you to select which ones to obfuscate.

## Obfuscation Methods

### Faker

Blur comes with built-in support for [Faker](https://github.com/FakerPHP/Faker). You can use any Faker method by prefixing it with `faker:`:

```php
'columns' => [
    'name' => 'faker:name',
    'email' => 'faker:email',
    'phone' => 'faker:phoneNumber',
    'address' => 'faker:address',
    // See Faker documentation for more available methods
],
```

### Custom Obfuscators

You can create your own obfuscators by implementing the `Intermax\Blur\Contracts\Obfuscator` interface:

```php
<?php

namespace App\Obfuscators;

use Intermax\Blur\Contracts\Obfuscator;

class FixedStringObfuscator implements Obfuscator
{
    public function generate(?array $parameters = null): mixed
    {
        return $parameters[0] ?? 'default-value';
    }
}
```

Then use it in your configuration:

```php
'columns' => [
    'some_field' => App\Obfuscators\FixedStringObfuscator::class.':custom-value',
],
```

## Performance Considerations

Blur processes records in chunks. You can adjust the `chunk_size` in the configuration to balance between memory usage and performance.


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
