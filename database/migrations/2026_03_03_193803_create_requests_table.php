<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('clientName');
            $table->string('phone');
            $table->string('address');
            $table->text('problemText');
            $table->enum('status', ['new', 'assigned', 'in_progress', 'done', 'canceled'])->default('new');
            $table->foreignId('assignedTo')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); // createdAt, updatedAt
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
