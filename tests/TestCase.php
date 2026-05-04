<?php

namespace Tests;

use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The trait's per-class static field-id cache survives transaction rollbacks;
        // each class using HasTranslations gets its own copy, so clear them all.
        foreach ([Restaurant::class, MenuSection::class, MenuItem::class, MenuOptionGroup::class, MenuOptionGroupOption::class, Zone::class] as $class) {
            $class::clearTranslationFieldCache();
        }
    }
}
