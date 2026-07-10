<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_marketplace_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('servers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('plugin_name')->nullable();
            $table->string('repository')->nullable();
            $table->string('project_id')->nullable();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_marketplace_jobs');
    }
};
