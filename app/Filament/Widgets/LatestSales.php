<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestSales extends BaseWidget
{
    protected static ?string $heading = 'Últimas 5 Ventas';
    protected static ?int $sort = 2;
    
    // Ocupar todo el ancho
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()
                    ->latest('created_at') // Las más recientes primero
                    ->limit(5) // Solo las últimas 5
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Hora')
                    ->dateTime('H:i A') // Solo mostramos la hora
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('items.product.name')
                    ->label('Producto')
                    ->limit(30), // Cortar nombres muy largos

                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->colors(['success' => 'store', 'warning' => 'server'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'store' => 'Tienda',
                        'server' => 'Servidor',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD')
                    ->color('success')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'gray',
                        'cancelled' => 'danger',
                    }),
            ])
            ->actions([
                // Botón pequeño para ver detalle rápido
                Tables\Actions\Action::make('ver')
                    ->url(fn (Sale $record): string => route('filament.admin.resources.sales.edit', $record))
                    ->icon('heroicon-m-eye')
                    ->color('gray'),
            ])
            ->paginated(false); // Sin paginación, solo lista fija
    }
}