<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();

            // Per-pairing boost: "berita" may rank link A first even though
            // link B outranks it globally.
            $table->integer('weight')->default(0);

            $table->unique(['keyword_id', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_link');
    }
};
