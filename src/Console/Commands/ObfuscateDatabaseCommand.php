<?php

declare(strict_types=1);

namespace Intermax\Blur\Console\Commands;

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

        $tableNames = DB::connection()->getDoctrineSchemaManager()->listTableNames();

        foreach ($tableNames as $tableName) {
            if (! in_array($tableName, array_keys(config('obfuscate.tables')))) {
                continue;
            }

            $chunkSize = config('obfuscate.tables.' . $tableName . '.chunk_size', 2000);

            $keys = config('obfuscate.tables.' . $tableName . '.keys');

            if ($keys !== null) {
                $primaryColumns = $keys;
            } else {
                $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes($tableName);

                $primaryColumns = $indexes['primary']->getColumns();
            }

            $progress = progress(label: 'Obfuscating table ' . $tableName . '...', steps: DB::table($tableName)->count());

            DB::table($tableName)->orderBy($primaryColumns[0])->chunk($chunkSize, function ($records) use ($tableName, $progress, $primaryColumns) {
                $continueFromTable = $this->option('continue-from-table');
                if ($continueFromTable !== null) {

                }
                $fields = config('obfuscate.tables.'.$tableName.'.columns', []);

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
        $modifiers = config('obfuscate.tables.'.$tableName.'.modifiers', []);

        foreach ($modifiers as $modifierClass) {
            $modifier = App::make($modifierClass);

            $update = $modifier->modify($update);
        }

        return $update;
    }
}
