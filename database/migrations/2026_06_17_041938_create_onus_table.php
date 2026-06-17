<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $blueprint->string('olt_index'); // e.g., gpon-olt_1/5/1
            $blueprint->string('onu_index')->nullable(); // e.g., gpon-onu_1/5/1:1
            $blueprint->string('sn')->unique();
            $blueprint->string('name')->nullable();
            $blueprint->string('model')->nullable();
            $blueprint->string('vlan')->nullable();
            $blueprint->enum('status', ['unconfigured', 'registered', 'active', 'inactive'])->default('unconfigured');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
