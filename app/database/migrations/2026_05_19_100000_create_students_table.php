<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('guardian_name')->nullable();
            $table->string('phone_primary_e164', 20)->nullable()->index();
            $table->string('phone_secondary_e164', 20)->nullable();
            $table->string('preferred_language', 5)->default('nl');
            $table->boolean('is_blocked_messages')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable()->index();
            $table->string('name');
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();

            $table->string('phone_primary_raw')->nullable();
            $table->string('phone_primary_e164', 20)->nullable()->index();
            $table->string('phone_secondary_raw')->nullable();
            $table->string('phone_secondary_e164', 20)->nullable();

            $table->boolean('allow_sms')->default(true);
            $table->boolean('allow_whatsapp')->default(true);
            $table->boolean('included_in_send_all')->default(true);

            $table->boolean('is_hidden')->default(false)->index();
            $table->boolean('is_blocked_messages')->default(false)->index();
            $table->boolean('is_in_person')->default(false)->index();
            $table->boolean('excluded_from_send_all')->default(false);

            $table->decimal('default_fee_amount', 8, 2)->nullable();
            $table->text('notes')->nullable();

            $table->date('enrolled_at')->nullable();
            $table->date('withdrawn_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_hidden', 'is_blocked_messages', 'is_in_person']);
        });

        Schema::create('student_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_suspensions');
        Schema::dropIfExists('students');
        Schema::dropIfExists('families');
    }
};
