<?php

namespace App\Services;

use App\Models\Cafe;
use App\Models\Branch;

class TenantContext
{
    protected ?Cafe $cafe = null;
    protected ?Branch $branch = null;

    public function setCafe(?Cafe $cafe): void
    {
        $this->cafe = $cafe;
    }

    public function getCafe(): ?Cafe
    {
        return $this->cafe;
    }

    public function getCafeId(): ?int
    {
        return $this->cafe?->id;
    }

    public function setBranch(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function getBranchId(): ?int
    {
        return $this->branch?->id;
    }

    public function hasTenant(): bool
    {
        return $this->cafe !== null;
    }

    public function clear(): void
    {
        $this->cafe = null;
        $this->branch = null;
    }
}
