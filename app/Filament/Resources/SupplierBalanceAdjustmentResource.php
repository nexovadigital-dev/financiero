<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierBalanceAdjustmentResource\Pages;
use App\Models\SupplierBalanceTransaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierBalanceAdjustmentResource extends Resource
{
    protected static ?string $model = SupplierBalanceTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Ajustes de Balance';
    protected static ?string $modelLabel = 'Ajuste de Balance';
    protected static ?string $pluralModelLabel = 'Ajustes de Balance';
    protected static ?string $navigationGroup = 'Proveedores';
    protected static ?int $navigationSort = 3;

    // Filtrar solo ajustes manuales
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'manual_adjustment')
            ->with(['supplier', 'user']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Solo lectura
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha del Ajuste')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Ajuste')
                    ->formatStateUsing(fn ($state) =>
                        ($state >= 0 ? '+' : '') . number_format($state, 2) . ' USD'
                    )
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->icon(fn ($state) => $state >= 0 ? 'heroicon-o-arrow-up' : 'heroicon-o-arrow-down')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Balance Anterior')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance Nuevo')
                    ->money('USD')
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Motivo del Ajuste')
                    ->searchable()
                    ->wrap()
                    ->weight('medium')
                    ->color('info'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->default('Desconocido')
                    ->icon('heroicon-o-user')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship('supplier', 'name')
                    ->placeholder('Todos los proveedores')
                    ->preload(),

                Tables\Filters\Filter::make('positive_adjustments')
                    ->label('Solo Aumentos (+)')
                    ->query(fn ($query) => $query->where('amount', '>', 0)),

                Tables\Filters\Filter::make('negative_adjustments')
                    ->label('Solo Reducciones (-)')
                    ->query(fn ($query) => $query->where('amount', '<', 0)),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Detalle del Ajuste de Balance'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierBalanceAdjustments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Se crean desde el botón en SupplierResource
    }
}
