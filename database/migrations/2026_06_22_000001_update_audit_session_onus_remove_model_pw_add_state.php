<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_session_onus', function (Blueprint $blueprint) {
            $blueprint->dropColumn('state');
            $blueprint->string('model')->default('');
            $blueprint->string('pw')->default('');
        });
    }

    public function down(): void
    {
        Schema::table('audit_session_onus', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['model', 'pw']);
            $blueprint->string('state')->default('unknown');
        });
    }
};
