<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_session_onus', function (Blueprint $table) {
            $table->string('model')->default('')->after('sn');
            $table->string('pw')->default('')->after('model');
            $table->dropColumn('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_session_onus', function (Blueprint $table) {
            $table->string('state')->default('unknown')->after('sn');
            $table->dropColumn(['model', 'pw']);
        });
    }
};
