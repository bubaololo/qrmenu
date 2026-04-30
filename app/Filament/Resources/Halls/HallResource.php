<?php

namespace App\Filament\Resources\Halls;

use App\Filament\Resources\Halls\Pages\CreateHall;
use App\Filament\Resources\Halls\Pages\EditHall;
use App\Filament\Resources\Halls\Pages\ListHalls;
use App\Filament\Resources\Halls\RelationManagers\TablesRelationManager;
use App\Filament\Resources\Halls\Schemas\HallForm;
use App\Filament\Resources\Halls\Tables\HallsTable;
use App\Models\Zone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HallResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Halls';

    protected static string|\UnitEnum|null $navigationGroup = 'Restaurant';

    public static function form(Schema $schema): Schema
    {
        return HallForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HallsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHalls::route('/'),
            'create' => CreateHall::route('/create'),
            'edit' => EditHall::route('/{record}/edit'),
        ];
    }
}
