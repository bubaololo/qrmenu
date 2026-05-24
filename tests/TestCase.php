<?php

namespace Tests;

use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The trait's per-class static field-id cache survives transaction rollbacks;
        // each class using HasTranslations gets its own copy, so clear them all.
        foreach ([MenuSection::class, MenuItem::class, MenuOptionGroup::class, MenuOptionGroupOption::class] as $class) {
            $class::clearTranslationFieldCache();
        }
    }
}
