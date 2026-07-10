<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_marketplace_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('hangar_enabled')->default(true);
            $table->boolean('modrinth_enabled')->default(true);
            $table->boolean('spigot_enabled')->default(true);
            $table->string('preferred_repository')->default('hangar');

            $table->boolean('automatic_update_checks')->default(true);
            $table->unsignedInteger('cache_duration')->default(30);
            $table->unsignedInteger('max_download_size')->default(250);
            $table->unsignedInteger('download_timeout')->default(120);

            $table->boolean('dependency_installation_enabled')->default(true);
            $table->boolean('health_warnings_enabled')->default(true);
            $table->boolean('backups_enabled')->default(true);
            $table->boolean('update_notifications_enabled')->default(true);

            $table->timestamps();
        });

        // Seed the single settings row up front so the settings page and
        // MarketplaceSettingsService never have to special-case "no row yet".
        DB::table('plugin_marketplace_settings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_marketplace_settings');
    }
};
