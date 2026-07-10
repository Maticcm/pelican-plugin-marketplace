<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_marketplace_installed_plugins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();

            $table->string('file_name');
            $table->string('name');
            $table->string('version')->nullable();
            $table->json('authors')->nullable();
            $table->text('description')->nullable();
            $table->string('main_class')->nullable();
            $table->string('api_version')->nullable();
            $table->json('depend')->nullable();
            $table->json('softdepend')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('enabled')->default(true);

            // Marketplace provenance - only populated when the jar was
            // installed (or later matched) through this plugin, which is
            // what allows update-checking to work for it.
            $table->string('repository')->nullable();
            $table->string('project_id')->nullable();
            $table->string('version_id')->nullable();
            $table->string('checksum')->nullable();
            $table->string('latest_version')->nullable();
            $table->boolean('update_available')->default(false);

            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'file_name']);
            $table->index(['server_id', 'update_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_marketplace_installed_plugins');
    }
};
