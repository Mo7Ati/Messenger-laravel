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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('email');
            $table->string('phone')->unique()->nullable()->after('username');
            $table->boolean('is_discoverable')->default(true)->after('phone');
            $table->text('bio')->nullable()->after('is_discoverable');
            $table->string('avatar')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'phone', 'is_discoverable', 'bio', 'avatar']);
        });
    }
};
