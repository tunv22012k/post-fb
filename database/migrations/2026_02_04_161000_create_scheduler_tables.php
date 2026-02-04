<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Owner
            $table->string('platform_id')->unique(); // Facebook Page ID
            $table->string('name');
            $table->text('access_token'); // Encrypted
            $table->string('platform_type')->default('facebook_page');
            $table->string('category_tag')->nullable();
            $table->timestamps();
        });

        Schema::create('master_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->longText('content');
            $table->string('source_url')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, PROCESSED
            $table->timestamps();
        });

        Schema::create('content_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_id')->constrained('master_articles')->onDelete('cascade');
            $table->longText('final_content');
            $table->string('source_type')->default('ORIGINAL'); // ORIGINAL, AI_REWRITE
            $table->string('status')->default('WAITING_REVIEW'); // WAITING_REVIEW, APPROVED
            $table->json('media_assets')->nullable();
            $table->timestamps();
        });

        Schema::create('publication_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('content_variants')->onDelete('cascade');
            $table->foreignId('channel_id')->constrained('destination_channels')->onDelete('cascade');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('QUEUED'); // QUEUED, PUBLISHING, PUBLISHED, FAILED
            $table->string('platform_response_id')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_jobs');
        Schema::dropIfExists('content_variants');
        Schema::dropIfExists('master_articles');
        Schema::dropIfExists('destination_channels');
    }
};
