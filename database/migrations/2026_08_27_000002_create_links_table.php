<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();

            // The public identity of this entry. Both /s/{slug} (share) and
            // /go/{slug} (on-site click) resolve through it, so it must never
            // be reused after deletion - a recycled slug would silently point
            // links people already shared at an unrelated destination.
            $table->string('slug')->unique();

            $table->string('title');
            $table->text('url');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('thumbnail_path')->nullable();

            // Manual ranking boost, applied on top of relevance.
            $table->integer('weight')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_reviewed')->default(false);

            // Denormalised counters, so the admin tables can sort cheaply
            // without aggregating link_clicks on every page load.
            $table->unsignedBigInteger('click_count')->default(0);
            $table->unsignedBigInteger('share_open_count')->default(0);

            // Materialised "title + description + every attached keyword".
            // Rebuilt by LinkObserver whenever the link or its keywords change.
            // This is what lets ranking be one indexed FULLTEXT query instead
            // of a join-and-score across the keyword pivot at 100k rows.
            $table->text('search_blob')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'weight']);
            $table->index(['is_active', 'is_reviewed']);
            $table->fullText('search_blob');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
