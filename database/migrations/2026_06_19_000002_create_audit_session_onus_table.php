<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_session_onus', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('audit_session_id')->constrained()->cascadeOnDelete();
            $blueprint->string('olt_index');
            $blueprint->string('onu_index')->nullable();
            $blueprint->string('sn');
            $blueprint->string('model');
            $blueprint->string('pw');
            $blueprint->timestamp('scanned_at');
            $blueprint->timestamps();

            $blueprint->index('audit_session_id');
            $blueprint->index('sn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_session_onus');
    }
};
