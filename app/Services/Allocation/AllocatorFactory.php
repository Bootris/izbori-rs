<?php

declare(strict_types=1);

namespace App\Services\Allocation;

use App\Enums\AllocationMethod;
use App\Models\Election;

final class AllocatorFactory
{
    public function for(Election $election): Allocator
    {
        return match ($election->allocation) {
            AllocationMethod::DHondt => new DHondtAllocator(),
            AllocationMethod::MajorityRunoff => new MajorityRunoffAllocator(),
        };
    }
}
