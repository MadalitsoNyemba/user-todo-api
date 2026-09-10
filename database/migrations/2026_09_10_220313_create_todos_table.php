<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->nullable();
            $table->timestamps();

            // List endpoint and bulk-complete both filter by owner + completion.
            $table->index(['user_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
