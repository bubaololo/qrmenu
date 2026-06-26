<?php

namespace App\Observers;

use App\Actions\ForgetMenuPageCache;
use App\Models\ModifierGroup;

class ModifierGroupObserver
{
    public function __construct(private ForgetMenuPageCache $pageCache) {}

    public function saved(ModifierGroup $group): void
    {
        $this->pageCache->forModel($group);
    }

    public function deleted(ModifierGroup $group): void
    {
        $this->pageCache->forModel($group);
    }
}
