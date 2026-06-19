<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_sessions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->nullOnDelete();
            $blueprint->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $blueprint->string('name');
            $blueprint->enum('status', ['active', 'completed'])->default('active');
            $blueprint->timestamp('started_at');
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->integer('onu_count')->default(0);
            $blueprint->timestamps();

            $blueprint->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_sessions');
    }
};
