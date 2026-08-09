<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Utilities;

/**
 * SafeMath
 *
 * Centralized numeric guards used by every engine: never divide by
 * zero, never return NaN/Infinity, never let a ratio escape its valid
 * range. Ported unchanged from the production backend.
 */
final class SafeMath
{
    /**
     * Divide two floats defensively, used only for RATIOS (never money —
     * money always uses Money::divideBy with integer minor units).
     */
    public static function safeDivideFloat(float $numerator, float $denominator, float $fallback = 0.0): float
    {
        if ($denominator === 0.0 || !is_finite($denominator)) {
            return $fallback;
        }
        $result = $numerator / $denominator;
        return self::finiteOrFallback($result, $fallback);
    }

    public static function finiteOrFallback(float $value, float $fallback = 0.0): float
    {
        if (is_nan($value) || is_infinite($value)) {
            return $fallback;
        }
        return $value;
    }

    /** Clamp a ratio/score into [0, $max] — used for confidence/stress scores expressed 0-100. */
    public static function clamp(float $value, float $min = 0.0, float $max = 100.0): float
    {
        $value = self::finiteOrFallback($value, $min);
        return max($min, min($max, $value));
    }

    /** Guarantee a strictly positive integer divisor (days-until-income, etc). */
    public static function safeDivisor(int $days, int $fallback = 30): int
    {
        return $days > 0 ? $days : $fallback;
    }

    public static function round2(float $value): float
    {
        return round(self::finiteOrFallback($value), 2);
    }
}
