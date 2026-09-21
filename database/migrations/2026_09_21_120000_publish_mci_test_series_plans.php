<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $plans = [
            ['name' => 'Abhyas Plan', 'name_hi' => 'अभ्यास Plan', 'slug' => 'abhyas-plan', 'description' => 'A focused starter package for regular practice.', 'price' => 199, 'test_limit' => 10, 'validity_days' => 30],
            ['name' => 'Taiyari Plan', 'name_hi' => 'तैयारी Plan', 'slug' => 'taiyari-plan', 'description' => 'Detailed preparation with analysis and rank comparison.', 'price' => 299, 'test_limit' => 25, 'validity_days' => 90],
            ['name' => 'Safalta Plan', 'name_hi' => 'सफलता Plan', 'slug' => 'safalta-plan', 'description' => 'Advanced preparation package for serious aspirants.', 'price' => 499, 'test_limit' => 60, 'validity_days' => 180],
            ['name' => 'Varshik Lakshya Plan', 'name_hi' => 'वार्षिक लक्ष्य Plan', 'slug' => 'varshik-lakshya-plan', 'description' => 'Best-value annual package for complete preparation.', 'price' => 799, 'test_limit' => 150, 'validity_days' => 365],
        ];

        foreach ($plans as $plan) {
            DB::table('packages')->updateOrInsert(
                ['slug' => $plan['slug']],
                [...$plan, 'exam_id' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('packages')->whereIn('slug', ['abhyas-plan', 'taiyari-plan', 'safalta-plan', 'varshik-lakshya-plan'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
};
