<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EC02 : matrice unique rôle × action. Table nouvelle, l'ancienne permissions_modules n'est pas touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('permission', 60);
            $table->timestamps();
            $table->unique(['role', 'permission'], 'app_permissions_role_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_permissions');
    }
};
