<?php
namespace App\Support;
class PlainText
{
    public static function safe(string $value): bool
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_check_encoding($value, 'UTF-8')
            && ! preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)
            && ! preg_match('/<\s*[!\/?a-z]/iu', $decoded);
    }
}
