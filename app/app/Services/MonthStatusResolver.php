<?php

namespace App\Services;

use App\Models\Student;
use Carbon\Carbon;

/**
 * يحسب حالة شهر معيّن لطالب:
 * - not_due  : الشهر مستقبلي لم يحن بعد
 * - paid     : الرصيد ≤ 0 (مدفوع كاملاً أو زيادة)
 * - partial  : دُفع جزء فقط
 * - late     : لم يدفع وقد مرّ منتصف الشهر (15)، أو وُسم legacy_late
 * - unpaid   : لم يدفع وحان وقت الشهر، لم يبلغ منتصفه
 * - legacy_zero : مستورد من الشيت بقيمة 0 (سُجّل بنكياً سابقاً)
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

        // فحص legacy_zero
        $hasLegacyZero = $student->payments()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('method', 'legacy_zero')
            ->exists();

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

        // لم يدفع — هل متأخر؟
        $hasLegacyLate = $student->markers()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('type', 'legacy_late')
            ->exists();

        $midOfNextMonth = $monthStart->copy()->addMonth()->day(15);
        if ($hasLegacyLate || $today->greaterThanOrEqualTo($midOfNextMonth)) {
            return 'late';
        }

        return 'unpaid';
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
