<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Utilities;

use InvalidArgumentException;

/**
 * Money
 *
 * Immutable value object representing an amount of currency as an
 * integer number of "minor units" (1 UZS = 100 minor units, same pattern
 * as cents/kopecks). Every arithmetic operation works on integers only,
 * so floating point rounding errors can never leak into a financial
 * calculation.
 *
 * Ported unchanged from the production backend — this class has no
 * dependency on the database, network, or any secret, so the demo uses
 * the exact real implementation.
 */
final class Money
{
    public const MAX_MAJOR_UNITS_ABS = 1_000_000_000_000; // 1 trillion

    private function __construct(
        private readonly int $minorUnits,
        private readonly string $currency
    ) {
    }

    public static function fromMinorUnits(int $minorUnits, string $currency = 'UZS'): self
    {
        return new self($minorUnits, $currency);
    }

    public static function fromMajorUnits(string|float|int $amount, string $currency = 'UZS'): self
    {
        $str = is_string($amount) ? $amount : number_format((float) $amount, 2, '.', '');
        if (!is_numeric($str)) {
            throw new InvalidArgumentException("Invalid money amount: {$str}");
        }

        if (stripos($str, 'e') !== false) {
            throw new InvalidArgumentException("Invalid money amount (scientific notation not supported): {$str}");
        }

        if (abs((float) $str) > self::MAX_MAJOR_UNITS_ABS) {
            throw new InvalidArgumentException(
                "Money amount {$str} exceeds the maximum supported magnitude of " . self::MAX_MAJOR_UNITS_ABS . ' major units.'
            );
        }

        $negative = str_starts_with($str, '-');
        $str = ltrim($str, '-');
        [$whole, $fraction] = array_pad(explode('.', $str, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $minor = ((int) $whole) * 100 + (int) $fraction;
        return new self($negative ? -$minor : $minor, $currency);
    }

    public static function zero(string $currency = 'UZS'): self
    {
        return new self(0, $currency);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function toMajorFloat(): float
    {
        return $this->minorUnits / 100;
    }

    public function toMajorString(): string
    {
        $negative = $this->minorUnits < 0;
        $abs = abs($this->minorUnits);
        $whole = intdiv($abs, 100);
        $frac = $abs % 100;
        return ($negative ? '-' : '') . $whole . '.' . str_pad((string) $frac, 2, '0', STR_PAD_LEFT);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /** Never returns negative money — clamps to zero. Used for "available to spend" outputs. */
    public function subtractClamped(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->minorUnits - $other->minorUnits;
        return new self(max(0, $result), $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorUnits > $other->minorUnits;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorUnits < $other->minorUnits;
    }

    /** Safe division; a non-positive divisor is coerced to 1, never divides by zero. */
    public function divideBy(int $divisor): self
    {
        $safeDivisor = $divisor > 0 ? $divisor : 1;
        return new self(intdiv($this->minorUnits, $safeDivisor), $this->currency);
    }

    /** Clamp any Money to a non-negative floor — final defense before API output. */
    public function clampNonNegative(): self
    {
        return new self(max(0, $this->minorUnits), $this->currency);
    }

    /**
     * @return array{minor_units:int, amount:string, currency:string}
     */
    public function toArray(): array
    {
        return [
            'minor_units' => $this->minorUnits,
            'amount'      => $this->toMajorString(),
            'currency'    => $this->currency,
        ];
    }

    public static function sum(string $currency, self ...$amounts): self
    {
        $total = self::zero($currency);
        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }
        return $total;
    }
}
