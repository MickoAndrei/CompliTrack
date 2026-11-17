<?php

namespace App\Support;

class IndependenceProcedure
{
    public static function meaningful(mixed $value): bool
    {
        if (!is_string($value)) { return false; }
        $text = trim($value);
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($text));
        return mb_strlen($text) >= 5 && preg_match('/\p{L}/u', $text)
            && !in_array($key, ['none', 'na', 'notapplicable', 'nil', 'null', 'tbd', 'tobeconfirmed', 'unknown', 'pending', 'test', 'placeholder'], true);
    }

    public static function rule(): \Closure
    {
        return static function ($attribute, $value, $fail) {
            if (!self::meaningful($value)) { $fail('Provide the applicable independence/conflict procedure code or meaningful reference. Placeholder-only values are not valid.'); }
        };
    }
}
