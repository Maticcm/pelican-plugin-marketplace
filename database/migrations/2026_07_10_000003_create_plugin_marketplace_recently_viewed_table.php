<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_marketplace_recently_viewed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('repository');
            $table->string('project_id');
            $table->string('slug');
            $table->string('name');
            $table->string('icon_url')->nullable();
            $table->timestamp('viewed_at');

            $table->timestamps();

            $table->unique(['user_id', 'repository', 'project_id']);
            $table->index(['user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_marketplace_recently_viewed');
    }
};
