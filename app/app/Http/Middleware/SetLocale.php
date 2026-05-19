<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public const SUPPORTED = ['en', 'ar', 'nl'];
    public const DEFAULT = 'en';

    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale', self::DEFAULT);
        if (!in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT;
        }
        App::setLocale($locale);
        return $next($request);
    }
}
