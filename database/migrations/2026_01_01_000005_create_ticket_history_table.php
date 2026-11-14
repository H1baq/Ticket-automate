<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ticket_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('action', 30);
            $table->enum('old_status', ['open', 'assigned', 'in_progress', 'resolved', 'closed'])
                ->nullable();
            $table->enum('new_status', ['open', 'assigned', 'in_progress', 'resolved', 'closed'])
                ->nullable();
            $table->string('details', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_history');
    }
};
