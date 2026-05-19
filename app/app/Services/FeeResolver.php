<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;

class FeeResolver
{
    public static function defaultMonthlyFee(): float
    {
        return (float) Setting::get('default_monthly_fee', 30.00);
    }

    /**
     * Resolve the applied fee for one student × month:
     * 1) per-student-month override
     * 2) student's default
     * 3) global default
     *
     * Reads from eager-loaded `feeOverrides` collection when available
     * to avoid N+1 queries inside grid renders.
     */
    public static function resolve(Student $student, int $year, int $month): float
    {
        if ($student->relationLoaded('feeOverrides')) {
            $override = $student->feeOverrides->first(
                fn ($o) => (int) $o->period_year === $year && (int) $o->period_month === $month
            );
        } else {
            $override = $student->feeOverrides()
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->first();
        }
        if ($override) {
            return (float) $override->amount;
        }

        if ($student->default_fee_amount !== null) {
            return (float) $student->default_fee_amount;
        }

        return self::defaultMonthlyFee();
    }

    public static function surchargesFor(Student $student, int $year, int $month): float
    {
        if ($student->relationLoaded('surcharges')) {
            return (float) $student->surcharges
                ->filter(fn ($s) => (int) $s->period_year === $year && (int) $s->period_month === $month)
                ->sum('amount');
        }

        return (float) $student->surcharges()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->sum('amount');
    }

    public static function dueAmount(Student $student, int $year, int $month): float
    {
        return self::resolve($student, $year, $month) + self::surchargesFor($student, $year, $month);
    }

    public static function paidAmount(Student $student, int $year, int $month): float
    {
        if ($student->relationLoaded('payments')) {
            return (float) $student->payments
                ->filter(fn ($p) => (int) $p->period_year === $year
                    && (int) $p->period_month === $month
                    && in_array($p->method, ['cash', 'bank'], true))
                ->sum('amount');
        }

        return (float) $student->payments()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('method', ['cash', 'bank'])
            ->sum('amount');
    }

    public static function balance(Student $student, int $year, int $month): float
    {
        return self::dueAmount($student, $year, $month) - self::paidAmount($student, $year, $month);
    }
}
