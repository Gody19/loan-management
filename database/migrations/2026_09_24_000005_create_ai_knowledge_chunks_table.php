<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->string('content_hash', 64)->index();
            $table->unsignedInteger('token_count')->nullable();
            $table->unsignedInteger('estimated_size')->nullable();
            $table->json('embedding')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['ai_knowledge_document_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};