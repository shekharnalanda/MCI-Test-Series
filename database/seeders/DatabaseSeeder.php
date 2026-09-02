<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ExamMasterSeeder::class,
            ImportantExamSeeder::class,
            PackageSeeder::class,
            AdminUserSeeder::class,
            DemoTestSeeder::class,
        ]);
    }
}
