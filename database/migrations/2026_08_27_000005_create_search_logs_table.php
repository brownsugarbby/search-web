<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query_raw');
            $table->string('query_normalized')->index();
            $table->unsignedInteger('result_count')->default(0);
            $table->foreignId('resolved_link_id')->nullable()->constrained('links')->nullOnDelete();

            // Where the request came from. 'share' is the one that matters most:
            // it separates word-of-mouth traffic from on-site search, and it is
            // what makes a dead shared link identifiable in the zero-result report.
            $table->enum('source', ['direct', 'lucky', 'share', 'suggest'])->default('direct');

            // Salted SHA-256. The raw IP is never stored.
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['query_normalized', 'created_at']);
            // Powers the zero-result report: WHERE result_count = 0 ORDER BY created_at.
            $table->index(['result_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
