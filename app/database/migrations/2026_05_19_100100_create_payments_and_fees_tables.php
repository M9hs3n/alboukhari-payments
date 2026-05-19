<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per (student × month) fee overrides
        Schema::create('student_monthly_fee_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 8, 2);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'period_year', 'period_month'], 'unique_override');
        });

        // Per (student × month) surcharges
        Schema::create('student_surcharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 8, 2);
            $table->string('reason');
            $table->timestamps();

            $table->index(['student_id', 'period_year', 'period_month']);
        });

        // The actual payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 8, 2);
            $table->date('paid_at');
            $table->enum('method', ['cash', 'bank', 'legacy_zero'])->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'period_year', 'period_month']);
        });

        // Markers — for imported "X" cells (late) without an actual payment
        Schema::create('student_monthly_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->enum('type', ['legacy_late', 'legacy_bank'])->index();
            $table->timestamps();

            $table->unique(['student_id', 'period_year', 'period_month', 'type'], 'unique_marker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_monthly_markers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('student_surcharges');
        Schema::dropIfExists('student_monthly_fee_overrides');
    }
};
