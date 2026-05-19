<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('language', 5)->default('nl');
            $table->text('body');
            $table->enum('default_for', ['first_friday', 'mid_month', 'none'])->default('none');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'send_all',
                'unpaid_by_month',
                'late_mid_month',
                'paid_less_than',
                'balance_above',
                'specific_students',
                'first_friday',
                'mid_month_auto',
            ]);
            $table->enum('status', ['draft', 'queued', 'running', 'paused', 'completed', 'failed', 'canceled'])->default('draft');
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->decimal('threshold_amount', 8, 2)->nullable();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body_template');
            $table->string('tag')->nullable();
            $table->boolean('group_by_family')->default(false);
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->decimal('estimated_cost', 10, 4)->default(0);
            $table->decimal('actual_cost', 10, 4)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_e164', 20);
            $table->text('body_personalized');
            $table->enum('status', ['pending', 'sending', 'sent', 'failed', 'skipped'])->index();
            $table->string('skip_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->unsignedTinyInteger('segments')->default(1);
            $table->decimal('cost', 8, 4)->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index(); // 'manual', 'send_all', 'first_friday', etc.
            $table->string('provider')->default('bulkgate');
            $table->string('phone', 20);
            $table->text('body');
            $table->unsignedTinyInteger('segments')->default(1);
            $table->string('status')->index();
            $table->decimal('cost', 8, 4)->default(0);
            $table->json('provider_response')->nullable();
            $table->string('tag')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('templates');
    }
};
