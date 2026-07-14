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
        Schema::create('summary_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('summary_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->integer('tokens_used')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('summary_chat_messages');
    }
};
