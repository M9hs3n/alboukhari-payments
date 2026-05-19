<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class HaltService
{
    public static function isHalted(): bool
    {
        return Setting::get('halt_sending', '0') === '1';
    }

    public static function halt(): void
    {
        Setting::put('halt_sending', '1');
    }

    public static function resume(): void
    {
        Setting::put('halt_sending', '0');
    }

    /**
     * Quota counter ساعي بسيط في الـ cache (يتلاشى تلقائياً بعد ساعتين).
     */
    public static function tryTakeQuota(int $count = 1): bool
    {
        $hourKey = 'bg:hour:' . date('YmdH');
        $used = (int) Cache::get($hourKey, 0);
        $max = (int) Setting::get('bulkgate_max_per_hour', 2500);

        if ($used + $count > $max) {
            return false;
        }

        Cache::put($hourKey, $used + $count, now()->addHours(2));
        return true;
    }

    public static function hourUsed(): int
    {
        return (int) Cache::get('bg:hour:' . date('YmdH'), 0);
    }
}
