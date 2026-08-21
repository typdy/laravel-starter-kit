<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('media');
    }

    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->nullable();
            $table->string('team');
            $table->string('project');

            $table->string('name')->nullable();
            $table->text('url')->nullable();
            $table->json('conversions')->nullable();
            $table->json('conversionsInProgress')->nullable();

            $table->json('translations')->nullable();
            $table->json('resource');

            $table->timestamp('created');
            $table->timestamp('updated');
        });
    }
};
