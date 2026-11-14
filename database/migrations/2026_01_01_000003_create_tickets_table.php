<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_no', 16)->unique();
            $table->foreignId('requester_id');
            $table->foreignId('agent_id')->nullable();
            $table->foreignId('category_id');
            $table->string('title', 150);
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high']);
            $table->enum('status', ['open', 'assigned', 'in_progress', 'resolved', 'closed'])
                ->default('open');
            $table->string('attachment_path')->nullable();
            $table->timestamp('due_at');
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index('agent_id');
            $table->index('requester_id');

            $table->foreign('requester_id')->references('id')->on('users');
            $table->foreign('agent_id')->references('id')->on('users');
            $table->foreign('category_id')->references('id')->on('categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
