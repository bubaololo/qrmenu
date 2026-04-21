<?php

namespace App\Models;

use App\Enums\DiningTableShape;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableShape extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'label', 'sort_order'];

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public static function idFor(DiningTableShape $shape): int
    {
        return (int) static::where('name', $shape->value)->value('id');
    }
}
