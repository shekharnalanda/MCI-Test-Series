<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Demo Test',
                'name_hi' => 'डेमो टेस्ट',
                'price' => 0,
                'test_limit' => 2,
                'validity_days' => 7,
            ],
            [
                'name' => '10 Test Pack',
                'name_hi' => '10 टेस्ट पैक',
                'price' => 0,
                'test_limit' => 10,
                'validity_days' => 30,
            ],
            [
                'name' => '20 Test Pack',
                'name_hi' => '20 टेस्ट पैक',
                'price' => 0,
                'test_limit' => 20,
                'validity_days' => 60,
            ],
            [
                'name' => '50 Test Pack',
                'name_hi' => '50 टेस्ट पैक',
                'price' => 0,
                'test_limit' => 50,
                'validity_days' => 90,
            ],
            [
                'name' => 'Unlimited Test Pack',
                'name_hi' => 'अनलिमिटेड टेस्ट पैक',
                'price' => 0,
                'test_limit' => null,
                'validity_days' => 365,
            ],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(
                ['slug' => Str::slug($package['name'])],
                $package + ['is_active' => true]
            );
        }
    }
}
