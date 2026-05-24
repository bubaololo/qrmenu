<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\AnalysisEventBroker;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()->with(['restaurant', 'diningTable', 'items']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('restaurant_name')
                    ->label('Restaurant')
                    ->state(fn (Order $r) => $r->restaurant?->name ?? "Restaurant #{$r->restaurant_id}"),

                TextColumn::make('diningTable.number')
                    ->label('Table')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'warning',
                        OrderStatus::InProgress => 'info',
                        OrderStatus::Completed => 'success',
                        OrderStatus::Cancelled => 'danger',
                    }),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (Order $r) => number_format($r->totalAmount(), 2)),

                TextColumn::make('placed_at')
                    ->label('Placed')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])->toArray()),

                SelectFilter::make('restaurant_id')
                    ->label('Restaurant')
                    ->options(fn () => Restaurant::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($r) => [$r->id => "#{$r->id} ".($r->name ?? "Restaurant #{$r->id}")])
                        ->toArray()
                    ),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $r): bool => ! $r->status->isTerminal())
                    ->action(function (Order $r): void {
                        $r->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);
                        app(AnalysisEventBroker::class)->publish(
                            "restaurant-orders.{$r->restaurant_id}",
                            'order.status-changed',
                            ['order_id' => $r->id, 'status' => OrderStatus::Completed->value],
                        );
                        Notification::make()->title('Order completed')->success()->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $r): bool => ! $r->status->isTerminal())
                    ->requiresConfirmation()
                    ->action(function (Order $r): void {
                        $r->update([
                            'status' => OrderStatus::Cancelled,
                            'cancelled_at' => now(),
                            'cancelled_reason' => 'Cancelled from admin panel',
                        ]);
                        app(AnalysisEventBroker::class)->publish(
                            "restaurant-orders.{$r->restaurant_id}",
                            'order.status-changed',
                            ['order_id' => $r->id, 'status' => OrderStatus::Cancelled->value],
                        );
                        Notification::make()->title('Order cancelled')->warning()->send();
                    }),
            ]);
    }
}
