<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olts', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->string('host');
            $blueprint->integer('port')->default(23);
            $blueprint->string('username');
            $blueprint->text('password'); // Encrypted
            $blueprint->string('olt_type')->default('ZTE C300');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
