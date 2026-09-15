<?php

declare(strict_types=1);

namespace App\Services\Allocation;

interface Allocator
{
    /**
     * @param array<int, array{id:int|string, votes:int, is_minority:bool}> $lists
     * @param int   $totalVoted   voters who voted (threshold base per Serbian law)
     * @param array{seats:int, threshold_pct:?float, minority_coef:?float} $rules
     */
    public function allocate(array $lists, int $totalVoted, array $rules): AllocationResult;
}
