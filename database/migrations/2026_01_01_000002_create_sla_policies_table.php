<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->enum('priority', ['low', 'medium', 'high'])->unique();
            $table->unsignedSmallInteger('resolution_hours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
