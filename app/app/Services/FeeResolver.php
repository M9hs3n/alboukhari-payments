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
     * يحسب الرسم المطبَّق لطالب لشهر:
     * 1) إن وُجد override لطالب × شهر => يستخدمه
     * 2) وإلا إن وُجد رسم خاص بالطالب => يستخدمه
     * 3) وإلا => الرسم الافتراضي العام
     */
    public static function resolve(Student $student, int $year, int $month): float
    {
        $override = $student->feeOverrides()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        if ($override) return (float) $override->amount;

        if ($student->default_fee_amount !== null) {
            return (float) $student->default_fee_amount;
        }

        return self::defaultMonthlyFee();
    }

    public static function surchargesFor(Student $student, int $year, int $month): float
    {
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
