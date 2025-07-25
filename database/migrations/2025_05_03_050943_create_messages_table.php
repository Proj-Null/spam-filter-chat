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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('sender_id')->nullable();// or uuid()
            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('receiver_id')->nullable();// or uuid()
            $table->foreign('receiver_id')->references('id')->on('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_spam')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
