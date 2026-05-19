<?php

namespace App\Services;

use App\Models\Student;
use Carbon\Carbon;

/**
 * Computes the status of a (student × year × month):
 *  - not_due     : month is in the future
 *  - paid        : balance <= 0 (full or overpaid)
 *  - partial     : something paid but less than due
 *  - late        : nothing paid and the month is past its grace window
 *  - unpaid      : nothing paid and the month is current/recent
 *  - legacy_zero : imported with method=legacy_zero (historically bank-paid)
 *
 * Reads from eager-loaded `payments` / `markers` collections when available
 * to avoid per-cell N+1 queries during grid renders.
 */
class MonthStatusResolver
{
    public static function resolve(Student $student, int $year, int $month): string
    {
        $today = Carbon::today();
        $monthStart = Carbon::create($year, $month, 1);
        if ($monthStart->greaterThan($today)) {
            return 'not_due';
        }

        $due = FeeResolver::dueAmount($student, $year, $month);

        $hasLegacyZero = self::hasPaymentMethod($student, $year, $month, 'legacy_zero');
        $paid = FeeResolver::paidAmount($student, $year, $month);

        if ($hasLegacyZero && $paid == 0) {
            return 'legacy_zero';
        }

        if ($due > 0 && $paid >= $due) {
            return 'paid';
        }

        if ($paid > 0 && $paid < $due) {
            return 'partial';
        }

        $hasLegacyLate = self::hasMarker($student, $year, $month, 'legacy_late');

        $midOfNextMonth = $monthStart->copy()->addMonth()->day(15);
        if ($hasLegacyLate || $today->greaterThanOrEqualTo($midOfNextMonth)) {
            return 'late';
        }

        return 'unpaid';
    }

    private static function hasPaymentMethod(Student $student, int $year, int $month, string $method): bool
    {
        if ($student->relationLoaded('payments')) {
            return $student->payments->contains(
                fn ($p) => (int) $p->period_year === $year
                    && (int) $p->period_month === $month
                    && $p->method === $method
            );
        }

        return $student->payments()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('method', $method)
            ->exists();
    }

    private static function hasMarker(Student $student, int $year, int $month, string $type): bool
    {
        if ($student->relationLoaded('markers')) {
            return $student->markers->contains(
                fn ($m) => (int) $m->period_year === $year
                    && (int) $m->period_month === $month
                    && $m->type === $type
            );
        }

        return $student->markers()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('type', $type)
            ->exists();
    }

    public static function colorClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-100 text-green-900',
            'partial' => 'bg-yellow-100 text-yellow-900',
            'unpaid' => 'bg-red-100 text-red-900',
            'late' => 'bg-red-200 text-red-950 font-bold',
            'legacy_zero' => 'bg-blue-100 text-blue-900',
            'not_due' => 'bg-gray-50 text-gray-400',
            default => '',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'paid' => 'مدفوع',
            'partial' => 'جزئي',
            'unpaid' => 'لم يدفع',
            'late' => 'متأخر',
            'legacy_zero' => 'بنكي 0',
            'not_due' => '—',
            default => $status,
        };
    }
}
