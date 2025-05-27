<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('obfuscates the database with faker data', function () {
    insertUsers();

    Config::set('blur.tables', [
        'users' => [
            'columns' => [
                'username' => 'faker:userName',
                'name' => 'faker:name',
            ],
        ],
    ]);

    $users = DB::table('users')->orderBy('id')->get();

    artisan('blur:obfuscate')->assertOk();

    $obfuscatedUsers = DB::table('users')->orderBy('id')->get();

    foreach ($users as $index => $user) {
        expect($user)
            ->username->not->toBe($obfuscatedUsers[$index]->username)
            ->name->not->toBe($obfuscatedUsers[$index]->name)
            ->password->toBe($obfuscatedUsers[$index]->password)
            ->created_at->toBe($obfuscatedUsers[$index]->created_at)
            ->updated_at->toBe($obfuscatedUsers[$index]->updated_at);
    }
});

function insertUsers(): void
{
    DB::table('users')->insert([
        [
            'id' => 1,
            'username' => 'johndoe',
            'name' => 'John Doe',
            'password' => bcrypt('Password123'),
            'created_at' => '2023-10-01 12:34:56',
            'updated_at' => '2023-10-01 12:34:56',
        ],
        [
            'id' => 2,
            'username' => 'janesmith',
            'name' => 'Jane Smith',
            'password' => bcrypt('SecurePass45'),
            'created_at' => '2023-09-25 08:15:30',
            'updated_at' => '2023-10-01 14:20:42',
        ],
        [
            'id' => 3,
            'username' => 'michaelb',
            'name' => 'Michael Brown',
            'password' => bcrypt('Michael_123'),
            'created_at' => '2023-09-15 10:00:00',
            'updated_at' => '2023-09-20 14:18:17',
        ],
        [
            'id' => 4,
            'username' => 'emilywhite',
            'name' => 'Emily White',
            'password' => bcrypt('Emily!@#'),
            'created_at' => '2023-08-10 16:45:22',
            'updated_at' => '2023-09-18 15:30:45',
        ],
        [
            'id' => 5,
            'username' => 'davemartin',
            'name' => 'Dave Martin',
            'password' => bcrypt('Dave2023'),
            'created_at' => '2023-07-05 13:30:00',
            'updated_at' => '2023-09-01 19:45:10',
        ],
    ]);
}
