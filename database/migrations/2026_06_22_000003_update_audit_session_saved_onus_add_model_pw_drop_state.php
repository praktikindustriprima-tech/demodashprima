<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_session_saved_onus', function (Blueprint $blueprint) {
            $blueprint->string('model')->after('sn');
            $blueprint->string('pw')->after('model');
            $blueprint->dropColumn('state');
        });
    }

    public function down(): void
    {
        Schema::table('audit_session_saved_onus', function (Blueprint $blueprint) {
            $blueprint->string('state')->default('unknown')->after('sn');
            $blueprint->dropColumn(['model', 'pw']);
        });
    }
};
