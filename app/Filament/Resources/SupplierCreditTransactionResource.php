<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierCreditTransactionResource\Pages;
use App\Models\SupplierBalanceTransaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierCreditTransactionResource extends Resource
{
    protected static ?string $model = SupplierBalanceTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Transacciones de Créditos';
    protected static ?string $modelLabel = 'Transacción de Crédito';
    protected static ?string $pluralModelLabel = 'Transacciones de Créditos';
    protected static ?string $navigationGroup = 'Proveedores';
    protected static ?int $navigationSort = 2;

    // Filtrar solo transacciones de ventas (sale_debit y sale_refund)
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', ['sale_debit', 'sale_refund'])
            ->with(['supplier', 'user', 'reference']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Solo lectura, no se pueden crear/editar
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->type_name)
                    ->color(fn ($record) => $record->type_color)
                    ->icon(fn ($record) => $record->type_icon),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Pedido/Venta')
                    ->formatStateUsing(function ($record) {
                        if ($record->reference_type === 'App\\Models\\Sale') {
                            return "Venta #{$record->reference_id}";
                        }
                        return '-';
                    })
                    ->url(function ($record) {
                        if ($record->reference_type === 'App\\Models\\Sale') {
                            return route('filament.admin.resources.sales.edit', ['record' => $record->reference_id]);
                        }
                        return null;
                    })
                    ->color('info')
                    ->icon('heroicon-o-shopping-cart'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn ($state) =>
                        ($state >= 0 ? '+' : '') . number_format($state, 2) . ' USD'
                    )
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Balance Anterior')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance Nuevo')
                    ->money('USD')
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->icon('heroicon-o-user')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship('supplier', 'name')
                    ->placeholder('Todos los proveedores')
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Transacción')
                    ->options([
                        'sale_debit' => '💳 Venta a Crédito',
                        'sale_refund' => '↩️ Reembolso de Venta',
                    ])
                    ->placeholder('Todos los tipos'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Detalle de Transacción de Crédito'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierCreditTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // No se pueden crear manualmente
    }
}
