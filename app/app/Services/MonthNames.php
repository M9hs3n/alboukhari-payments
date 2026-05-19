<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class MonthNames
{
    private const FALLBACK = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    /** @var array<string, array<int, string>> */
    private static array $cache = [];

    /**
     * Locale-aware full month names, 1-indexed (1 => January / januari / يناير).
     * Falls back to Arabic if Carbon cannot translate the requested locale,
     * preserving the app's historical behaviour.
     *
     * @return array<int, string>
     */
    public static function full(?string $locale = null): array
    {
        $locale = $locale ?: App::getLocale();
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }
        try {
            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[$m] = Carbon::create(2000, $m, 1)->locale($locale)->translatedFormat('F');
            }
        } catch (\Throwable) {
            $months = self::FALLBACK;
        }
        return self::$cache[$locale] = $months;
    }
}
