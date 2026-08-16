<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const METHOD_NAMES = [
        'ლიბერთი ბანკი',
        'კრედო ბანკი',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('withdrawal_methods')) {
            return;
        }

        $methodFields = json_encode([
            [
                'input_type' => 'text',
                'input_name' => 'ანგარიშის_ნომერი',
                'placeholder' => 'GE00XX0000000000000000',
                'is_required' => 1,
            ],
        ], JSON_UNESCAPED_UNICODE);

        foreach (self::METHOD_NAMES as $methodName) {
            if (DB::table('withdrawal_methods')->where('method_name', $methodName)->exists()) {
                continue;
            }

            DB::table('withdrawal_methods')->insert([
                'method_name' => $methodName,
                'method_fields' => $methodFields,
                'is_active' => 1,
                'is_default' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep configured methods and vendor account references intact on rollback.
    }
};
