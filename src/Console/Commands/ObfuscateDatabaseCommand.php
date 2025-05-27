<?php

declare(strict_types=1);

namespace Intermax\Blur\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Intermax\Blur\Contracts\Obfuscator;
use Intermax\Blur\Obfuscators\FakerObfuscator;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\progress;

class ObfuscateDatabaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'blur:obfuscate {--i|interactive : Select which tables to obfuscate interactively}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obfuscate sensitive database fields';

    /**
     * @var array<string, Obfuscator>
     */
    private array $obfuscatorInstances = [];

    public function handle(): int
    {
        if (App::environment('production')) {
            $this->components->error('Environment is production, stopping.');

            return 1;
        }

        $tableNames = Arr::pluck(DB::connection()->getSchemaBuilder()->getTables(), 'name');
        $configuredTables = array_keys(config('blur.tables'));
        $tablesToProcess = [];

        // Filter tables that are configured in blur.tables
        foreach ($tableNames as $tableName) {
            if (in_array($tableName, $configuredTables)) {
                $tablesToProcess[$tableName] = $tableName;
            }
        }

        if (empty($tablesToProcess)) {
            $this->components->error('No tables configured for obfuscation. Check your blur.php config file.');

            return 1;
        }

        // If interactive mode is enabled, let the user select which tables to obfuscate
        if ($this->option('interactive')) {
            $selectedTables = multiselect(
                label: 'Select tables to obfuscate',
                options: $tablesToProcess,
                scroll: 10,
                hint: 'Use the space bar to select tables to obfuscate.'
            );

            if (empty($selectedTables)) {
                $this->components->info('No tables selected for obfuscation.');

                return 0;
            }

            $tablesToProcess = array_intersect_key($tablesToProcess, array_flip($selectedTables));
        }

        foreach ($tablesToProcess as $tableName) {
            $chunkSize = config('blur.tables.'.$tableName.'.chunk_size', 2000);

            $keys = config('blur.tables.'.$tableName.'.keys');

            if ($keys !== null) {
                $primaryColumns = $keys;
            } else {
                $indexes = DB::connection()->getSchemaBuilder()->getIndexes($tableName);

                $primaryColumns = Arr::where($indexes, fn ($value) => $value['name'] === 'primary')['columns'] ?? ['id'];
            }

            $method = config('blur.tables.'.$tableName.'.method', 'update');

            if ($method === 'clear') {
                DB::table($tableName)->delete();

                $this->components->info('Table '.$tableName.' cleared.');

                continue;
            }

            $this->obfuscateTable($tableName, $primaryColumns, $chunkSize);
        }

        $this->components->info('Database obfuscation finished.');

        return 0;
    }

    public function obfuscate(string $key): mixed
    {
        $key = str($key);

        $name = $key->before(':');
        $nameStr = $name->toString();

        if (! isset($this->obfuscatorInstances[$nameStr])) {
            if ($name->is('faker')) {
                $this->obfuscatorInstances[$nameStr] = new FakerObfuscator;
            } else {
                $this->obfuscatorInstances[$nameStr] = App::make($nameStr);
            }
        }

        $parameters = $key->after(':')->explode(',')->toArray();

        return $this->obfuscatorInstances[$nameStr]->generate($parameters);
    }

    /**
     * @param  array<string, mixed>  $update
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

    /**
     * @param  array<int, string>  $primaryColumns
     */
    private function obfuscateTable(string $tableName, array $primaryColumns, int $chunkSize): void
    {
        $count = DB::table($tableName)->count();

        if ($count === 0) {
            $this->components->info('Table "'.$tableName.'" is empty.');

            return;
        }

        $progress = progress(label: 'Obfuscating table '.$tableName.'...', steps: $count);

        DB::table($tableName)->orderBy($primaryColumns[0])->chunkById($chunkSize, function (Collection $records) use ($tableName, $progress, $primaryColumns, $chunkSize) {
            $fields = config('blur.tables.'.$tableName.'.columns', []);

            $batchSize = min(50, $chunkSize);
            $updates = [];
            $processedCount = 0;

            foreach ($records as $record) {
                $update = [];

                foreach ((array) $record as $column => $value) {
                    $update[$column] = $value;
                }

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
                $processedCount++;

                // Process in smaller batches to reduce memory usage
                if (count($updates) >= $batchSize) {
                    DB::table($tableName)->upsert($updates, $primaryColumns, array_keys($fields));
                    $progress->advance($processedCount);

                    $updates = [];
                    $processedCount = 0;
                    unset($update);
                }

                unset($record);
            }

            // Process any remaining updates
            if (count($updates) > 0) {
                DB::table($tableName)->upsert($updates, $primaryColumns, array_keys($fields));
                $progress->advance($processedCount);
                unset($update);
            }

            unset($records);
        });

        $progress->finish();

        $this->obfuscatorInstances = [];
    }
}
