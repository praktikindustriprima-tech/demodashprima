<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_history', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $blueprint->string('action'); // e.g., 'Scan', 'Register', 'Reboot'
            $blueprint->string('target_sn')->nullable();
            $blueprint->text('command_sent');
            $blueprint->text('response_raw')->nullable();
            $blueprint->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_history');
    }
};
