<?php

declare(strict_types=1);

namespace Intermax\Blur\Console\Commands;

use Illuminate\Support\Arr;
use Intermax\Blur\Obfuscators\FakerObfuscator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\{info, progress};

class ObfuscateDatabaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'blur:obfuscate {--memory-limit=} {--continue-from-table=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obfuscate sensitive database fields';

    private ?string $continueFromTable = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $memoryLimit = $this->option('memory-limit');

        $this->continueFromTable = $this->option('continue-from-table');

        if ($memoryLimit !== null) {
            ini_set('memory_limit', $memoryLimit);
        }

        if (App::environment('production')) {
            $this->components->error('Environment is production, stopping.');
        }

        $tableNames = Arr::pluck(DB::connection()->getSchemaBuilder()->getTables(), 'name');

        foreach ($tableNames as $tableName) {
            if (! in_array($tableName, array_keys(config('blur.tables')))) {
                continue;
            }

            $chunkSize = config('blur.tables.' . $tableName . '.chunk_size', 2000);

            $keys = config('blur.tables.' . $tableName . '.keys');

            if ($keys !== null) {
                $primaryColumns = $keys;
            } else {
                $indexes = DB::connection()->getSchemaBuilder()->getIndexes($tableName);

                $primaryColumns = Arr::where($indexes, fn ($value) => $value['name'] === 'primary')['columns'] ?? ['id'];
            }

            $method = config('blur.tables.' . $tableName . '.method', 'upsert');

            if ($method === 'empty') {
                DB::table($tableName)->delete();

                $this->components->info('Table ' . $tableName . ' truncated.');

                continue;
            }

            $count = DB::table($tableName)->count();

            if ($count === 0) {
                $this->components->info('Table ' . $tableName . ' is empty.');

                continue;
            }

            $progress = progress(label: 'Obfuscating table ' . $tableName . '...', steps: $count);

            DB::table($tableName)->orderBy($primaryColumns[0])->chunkById(500, function ($records) use ($tableName, $progress, $primaryColumns) {
                $continueFromTable = $this->option('continue-from-table');
                if ($continueFromTable !== null) {

                }
                $fields = config('blur.tables.'.$tableName.'.columns', []);

                $updates = [];

                foreach ($records as $record) {
                    $update = [];

                    foreach ($primaryColumns as $column) {
                        $update[$column] = $record->$column;
                    }

                    if (count($fields) > 0) {
                        foreach ($fields as $field => $obfuscator) {
                            $update[$field] = $this->obfuscate($obfuscator);
                        }
                    }

                    $update = $this->applyModifiers($tableName, $update);

                    $updates[] = $update;
                }

                DB::table($tableName)->upsert($updates, $primaryColumns);

                $progress->advance(count($records));
            });

            $progress->finish();
        }

        $this->components->info('Database obfuscation finished.');

        return 0;
    }

    public function obfuscate(string $key): mixed
    {
        $key = str($key);

        $name = $key->before(':');

        if ($name->is('faker')) {
            $obfuscator = new FakerObfuscator();
        } else {
            $obfuscator = App::make($name->toString());
        }

        $parameters = $key->after(':')->explode(',')->toArray();

        return $obfuscator->generate($parameters);
    }

    /**
     * @param array<string, mixed> $update
     * @return array<string, mixed>
     */
    public function applyModifiers(string $tableName, array $update): array
    {
        $modifiers = config('blur.tables.'.$tableName.'.modifiers', []);

        foreach ($modifiers as $modifierClass) {
            $modifier = App::make($modifierClass);

            $update = $modifier->modify($update);
        }

        return $update;
    }
}
