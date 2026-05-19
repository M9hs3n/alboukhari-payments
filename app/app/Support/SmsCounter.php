<?php

namespace App\Support;

/**
 * عدّاد SMS — يحسب عدد الأحرف وعدد الـ Segments (الرسائل) بدقة BulkGate.
 *
 * - GSM-7 (إنجليزي/هولندي بعد ASCII): 160 لحرف واحدة، 153 للمتعدد.
 * - UCS-2 (عربي/Unicode): 70 لحرف واحدة، 67 للمتعدد.
 */
class SmsCounter
{
    /**
     * @return array{length:int,segments:int,encoding:'gsm'|'unicode',max_per_segment:int,sanitized:string}
     */
    public static function count(string $text, bool $forceAscii = true): array
    {
        $sanitized = $forceAscii ? AsciiSanitizer::sanitize($text) : $text;
        $isUnicode = !$forceAscii && self::hasUnicode($sanitized);

        if ($isUnicode) {
            $length = mb_strlen($sanitized, 'UTF-8');
            if ($length <= 70) {
                $segments = $length === 0 ? 0 : 1;
                $maxPerSegment = 70;
            } else {
                $segments = (int) ceil($length / 67);
                $maxPerSegment = 67;
            }
            $encoding = 'unicode';
        } else {
            $length = strlen($sanitized);
            if ($length <= 160) {
                $segments = $length === 0 ? 0 : 1;
                $maxPerSegment = 160;
            } else {
                $segments = (int) ceil($length / 153);
                $maxPerSegment = 153;
            }
            $encoding = 'gsm';
        }

        return [
            'length' => $length,
            'segments' => $segments,
            'encoding' => $encoding,
            'max_per_segment' => $maxPerSegment,
            'sanitized' => $sanitized,
        ];
    }

    public static function hasUnicode(string $text): bool
    {
        return (bool) preg_match('/[^\x00-\x7F]/', $text);
    }
}
