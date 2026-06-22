<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excluded_onus', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('sn')->unique();
            $blueprint->text('notes')->nullable();
            $blueprint->timestamps();

            $blueprint->index('sn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excluded_onus');
    }
};
