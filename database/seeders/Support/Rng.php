<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Deterministic random helpers on top of the global Mt19937 state, so a seed
 * run is reproducible (Faker draws from the same state — seed once, here).
 */
final class Rng
{
    public function __construct(int $seed)
    {
        mt_srand($seed);
    }

    public function int(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function float(float $min = 0.0, float $max = 1.0): float
    {
        return $min + ($max - $min) * mt_rand() / mt_getrandmax();
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /** Log-normal multiplier with mean ≈ 1 (Box–Muller). */
    public function jitter(float $sigma): float
    {
        $u1 = max(1e-12, $this->float());
        $u2 = $this->float();
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        return exp($sigma * $z - $sigma * $sigma / 2);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public function shuffle(array $items): array
    {
        shuffle($items); // draws from the same seeded Mt19937 state

        return $items;
    }
}
