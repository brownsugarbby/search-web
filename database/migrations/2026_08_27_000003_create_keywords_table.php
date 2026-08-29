<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();

            // What the admin typed, preserved for display.
            $table->string('keyword');

            // What we actually match against. Produced by QueryNormalizer, the
            // same class the incoming search query runs through, so the stored
            // form and the query form can never drift apart.
            $table->string('keyword_normalized')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
