<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Listado de Proveedores';
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $navigationGroup = 'Proveedores';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Proveedor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Proveedor')
                            ->required()
                            ->minLength(2)
                            ->maxLength(255)
                            ->placeholder('Ingrese el nombre del proveedor')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('website')
                            ->label('Sitio Web')
                            ->suffixIcon('heroicon-m-globe-alt')
                            ->placeholder('fusion.com')
                            ->helperText('Ingrese solo el dominio (ej: fusion.com)')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('payment_currency')
                            ->label('Tipo de Moneda de Pago')
                            ->options([
                                'USDT' => '💵 USDT (Criptomoneda)',
                                'LOCAL' => '💰 Moneda Local',
                            ])
                            ->default('LOCAL')
                            ->required()
                            ->helperText('Seleccione en qué moneda paga a este proveedor')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('balance')
                            ->label(fn ($record) => $record ? 'Balance Invertido Actual' : 'Balance Inicial')
                            ->prefix('$')
                            ->numeric()
                            ->default(0)
                            ->step(0.01)
                            ->minValue(0)
                            ->disabled(fn ($record) => $record !== null) // Solo editable al crear
                            ->dehydrated(fn ($record) => $record === null) // Solo guardar al crear
                            ->helperText(fn ($record) => $record
                                ? 'Balance se actualiza automáticamente con Pagos y Ventas de Créditos.'
                                : 'Si ya tiene dinero depositado con este proveedor, ingrese el saldo disponible.')
                            ->columnSpanFull(),
                    ])->columns(1), // Una sola columna para que se vea limpio
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('website')
                    ->label('Sitio Web')
                    ->icon('heroicon-m-link')
                    ->color('info')
                    ->url(fn ($state) => $state ? (str_starts_with($state, 'http') ? $state : 'https://' . $state) : null, true)
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_currency')
                    ->label('Tipo de Pago')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'USDT' => 'success',
                        'LOCAL' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'USDT' => '💵 USDT',
                        'LOCAL' => '💰 Local',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance Invertido')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->weight('bold')
                    ->description('Pagos - Créditos usados'),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust_balance')
                    ->label('Ajustar Balance')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\Radio::make('adjustment_type')
                            ->label('Tipo de Ajuste')
                            ->options([
                                'increase' => '⬆️ Aumentar Balance (Agregar Créditos)',
                                'decrease' => '⬇️ Reducir Balance (Debitar Créditos)',
                            ])
                            ->required()
                            ->default('increase')
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('adjustment')
                            ->label('Monto del Ajuste (USD)')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->helperText(fn (Forms\Get $get) =>
                                $get('adjustment_type') === 'increase'
                                    ? '💰 Este monto se AGREGARÁ al balance del proveedor'
                                    : '⚠️ Este monto se RESTARÁ del balance del proveedor'
                            )
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo del Ajuste')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ej: Corrección por error de sistema, Ajuste acordado con proveedor, etc.')
                            ->helperText('Explique claramente por qué está ajustando el balance (para auditoría)')
                            ->columnSpanFull(),
                    ])
                    ->action(function (Supplier $record, array $data) {
                        $oldBalance = $record->balance;
                        $adjustment = floatval($data['adjustment']);
                        $adjustmentType = $data['adjustment_type'];
                        $reason = $data['reason'];

                        // Ajustar balance según el tipo seleccionado
                        if ($adjustmentType === 'increase') {
                            $record->addToBalance(
                                amount: $adjustment,
                                type: 'manual_adjustment',
                                description: $reason,
                                reference: null
                            );
                        } else {
                            $record->subtractFromBalance(
                                amount: $adjustment,
                                type: 'manual_adjustment',
                                description: $reason,
                                reference: null
                            );
                        }

                        $newBalance = $record->fresh()->balance;

                        // Log detallado para auditoría
                        \Log::info('⚖️ Ajuste manual de balance de proveedor', [
                            'supplier_id' => $record->id,
                            'supplier_name' => $record->name,
                            'adjustment_type' => $adjustmentType,
                            'old_balance' => $oldBalance,
                            'adjustment' => $adjustmentType === 'increase' ? $adjustment : -$adjustment,
                            'new_balance' => $newBalance,
                            'reason' => $reason,
                            'user_id' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Balance Ajustado')
                            ->body("Balance de {$record->name} actualizado: \${$oldBalance} → \$" . number_format($newBalance, 2))
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Ajustar Balance del Proveedor')
                    ->modalDescription(fn (Supplier $record) =>
                        "Balance actual: $" . number_format($record->balance, 2) . " USD"
                    ),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Supplier $record) {
                        // Verificar si el proveedor tiene pagos asociados
                        if ($record->expenses()->count() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body('Este proveedor tiene pagos registrados. No es posible eliminarlo.')
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Proveedor')
                    ->modalDescription('¿Está seguro que desea eliminar este proveedor? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Proveedores Seleccionados')
                        ->modalDescription('¿Está seguro que desea eliminar los proveedores seleccionados? También se eliminarán todos los pagos asociados.')
                        ->modalSubmitActionLabel('Sí, eliminar todos'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}