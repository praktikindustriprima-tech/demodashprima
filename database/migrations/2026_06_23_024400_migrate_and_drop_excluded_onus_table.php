<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $globalExcluded = DB::table('excluded_onus')->orderBy('created_at')->get(['sn', 'notes'])->toArray();

        if (! empty($globalExcluded)) {
            $users = User::all();
            $now = now();
            $insert = [];

            foreach ($users as $user) {
                $insert[] = [
                    'user_id' => $user->id,
                    'key' => 'excluded_sns',
                    'value' => json_encode($globalExcluded),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('olt_preferences')->insert($insert);
        }

        Schema::dropIfExists('excluded_onus');
    }

    public function down(): void
    {
        Schema::create('excluded_onus', function (Blueprint $table) {
            $table->id();
            $table->string('sn')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('sn');
        });

        $excludedSns = DB::table('olt_preferences')
            ->where('key', 'excluded_sns')
            ->value('value');

        if ($excludedSns) {
            $items = json_decode($excludedSns, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    DB::table('excluded_onus')->insert([
                        'sn' => $item['sn'],
                        'notes' => $item['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
