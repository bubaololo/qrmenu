<?php

namespace App\Observers;

use App\Actions\ForgetMenuPageCache;
use App\Models\ModifierOption;

class ModifierOptionObserver
{
    public function __construct(private ForgetMenuPageCache $pageCache) {}

    public function saved(ModifierOption $option): void
    {
        $this->pageCache->forModel($option);
    }

    public function deleted(ModifierOption $option): void
    {
        $this->pageCache->forModel($option);
    }
}
