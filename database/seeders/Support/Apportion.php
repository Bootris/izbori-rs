<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

final class Apportion
{
    /**
     * Split an integer total over weights so the parts sum to exactly $total
     * (largest-remainder rounding). Keys are preserved.
     *
     * @param  array<int|string, float|int>  $weights
     * @return array<int|string, int>
     */
    public static function split(int $total, array $weights): array
    {
        $result = array_fill_keys(array_keys($weights), 0);
        $sum = array_sum($weights);

        if ($total <= 0 || $sum <= 0) {
            return $result;
        }

        $remainders = [];
        $assigned = 0;
        foreach ($weights as $key => $weight) {
            $exact = $total * $weight / $sum;
            $floor = (int) floor($exact);
            $result[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $assigned += $floor;
        }

        arsort($remainders);
        $left = $total - $assigned;
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $result[$key]++;
            $left--;
        }

        return $result;
    }
}
