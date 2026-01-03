<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Pagos a Proveedores';
    protected static ?string $modelLabel = 'Pago a Proveedor';
    protected static ?string $pluralModelLabel = 'Pagos a Proveedores';
    protected static ?string $navigationGroup = 'Gestión';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registrar Pago a Proveedor')
                    ->description('El pago se registrará en la moneda del proveedor y los créditos se sumarán a su balance.')
                    ->schema([
                        // 1. Selector de Proveedor (determina la moneda)
                        Forms\Components\Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(function () {
                                return Supplier::all()->mapWithKeys(function ($supplier) {
                                    $currency = $supplier->payment_currency === 'USDT' ? 'USDT' : 'NIO';
                                    $balance = number_format($supplier->balance, 2);
                                    return [$supplier->id => "{$supplier->name} ({$currency}) - Balance: {$balance}"];
                                });
                            })
                            ->required()
                            ->native(false)
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (!$state) {
                                    $set('currency', null);
                                    return;
                                }

                                $supplier = Supplier::find($state);
                                if ($supplier) {
                                    // Establecer moneda basada en el proveedor
                                    $currency = $supplier->payment_currency === 'USDT' ? 'USDT' : 'NIO';
                                    $set('currency', $currency);
                                }
                            })
                            ->helperText('Seleccione el proveedor para ver su moneda de pago'),

                        // Mostrar moneda del proveedor (solo lectura)
                        Forms\Components\Placeholder::make('supplier_currency_info')
                            ->label('Moneda de Pago')
                            ->content(function (Get $get) {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) {
                                    return 'Seleccione un proveedor';
                                }

                                $supplier = Supplier::find($supplierId);
                                if (!$supplier) {
                                    return 'Proveedor no encontrado';
                                }

                                $currency = $supplier->payment_currency === 'USDT' ? 'USDT (Dólares Tether)' : 'NIO (Córdobas)';
                                $balance = number_format($supplier->balance, 2);

                                return new \Illuminate\Support\HtmlString(
                                    "<div class='flex gap-4'>
                                        <span class='px-3 py-1 bg-blue-100 text-blue-800 rounded-full font-medium'>💱 {$currency}</span>
                                        <span class='px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium'>💰 Balance actual: {$balance} créditos</span>
                                    </div>"
                                );
                            })
                            ->visible(fn (Get $get) => !empty($get('supplier_id'))),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha del Pago')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('payment_method_id')
                            ->label('Método de Pago')
                            ->relationship('paymentMethod', 'name')
                            ->required()
                            ->native(false)
                            ->preload(),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referencia de Pago')
                            ->placeholder('Ej: Transfer-12345, TxID, Cheque #678')
                            ->maxLength(100),

                        // Campo oculto para la moneda
                        Forms\Components\Hidden::make('currency')
                            ->dehydrated(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                // 2. Monto pagado (en la moneda del proveedor)
                                Forms\Components\TextInput::make('amount')
                                    ->label(function (Get $get) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) {
                                            return 'Monto Pagado';
                                        }
                                        $supplier = Supplier::find($supplierId);
                                        $currency = $supplier?->payment_currency === 'USDT' ? 'USDT' : 'NIO';
                                        return "Monto Pagado ({$currency})";
                                    })
                                    ->numeric()
                                    ->prefix(function (Get $get) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) return '$';
                                        $supplier = Supplier::find($supplierId);
                                        return $supplier?->payment_currency === 'USDT' ? '$' : 'C$';
                                    })
                                    ->required()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->live(onBlur: true)
                                    ->helperText('💵 Cantidad real que está pagando/depositando al proveedor'),

                                // 3. Créditos recibidos (lo que se suma al balance)
                                Forms\Components\TextInput::make('credits_received')
                                    ->label('Créditos a Recibir')
                                    ->numeric()
                                    ->prefix('⭐')
                                    ->required()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->helperText('💳 Créditos que se sumarán al balance del proveedor'),
                            ]),

                        // Información del resultado
                        Forms\Components\Placeholder::make('result_info')
                            ->label('')
                            ->content(function (Get $get) {
                                $supplierId = $get('supplier_id');
                                $amount = floatval($get('amount') ?? 0);
                                $credits = floatval($get('credits_received') ?? 0);

                                if (!$supplierId || $amount <= 0) {
                                    return '';
                                }

                                $supplier = Supplier::find($supplierId);
                                if (!$supplier) return '';

                                $currency = $supplier->payment_currency === 'USDT' ? 'USDT' : 'NIO';
                                $symbol = $supplier->payment_currency === 'USDT' ? '$' : 'C$';
                                $newBalance = $supplier->balance + $credits;

                                return new \Illuminate\Support\HtmlString(
                                    "<div class='p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border'>
                                        <p class='font-semibold mb-2'>📊 Resumen de la operación:</p>
                                        <ul class='text-sm space-y-1'>
                                            <li>💸 <strong>Gasto registrado:</strong> {$symbol}" . number_format($amount, 2) . " {$currency}</li>
                                            <li>⭐ <strong>Créditos a recibir:</strong> " . number_format($credits, 2) . "</li>
                                            <li>📈 <strong>Nuevo balance:</strong> " . number_format($newBalance, 2) . " créditos</li>
                                        </ul>
                                    </div>"
                                );
                            })
                            ->visible(fn (Get $get) => !empty($get('supplier_id')) && floatval($get('amount') ?? 0) > 0)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Concepto / Detalle')
                            ->placeholder('Describe el motivo del pago...')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        // Campos ocultos para compatibilidad
                        Forms\Components\Hidden::make('amount_usd')
                            ->dehydrated()
                            ->default(0),
                        Forms\Components\Hidden::make('exchange_rate_used')
                            ->dehydrated()
                            ->default(1),
                        Forms\Components\Hidden::make('manually_converted')
                            ->dehydrated()
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->weight('bold')
                    ->description(fn ($record) => $record->supplier?->payment_currency === 'USDT' ? '💵 USDT' : '🇳🇮 NIO')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto Pagado')
                    ->formatStateUsing(function ($record) {
                        $symbol = $record->currency === 'USDT' ? '$' : 'C$';
                        return $symbol . number_format($record->amount, 2) . ' ' . ($record->currency ?? '');
                    })
                    ->color('danger'),

                Tables\Columns\TextColumn::make('credits_received')
                    ->label('Créditos')
                    ->formatStateUsing(fn ($state) => '⭐ ' . number_format($state ?? 0, 2))
                    ->color('success'),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Vía')
                    ->badge(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Concepto')
                    ->limit(25)
                    ->toggleable(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Proveedor')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Pago')
                    ->modalDescription('¿Está seguro que desea eliminar este pago? Los créditos se revertirán del balance del proveedor.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Pagos Seleccionados')
                        ->modalDescription('¿Está seguro? Los créditos se revertirán de los balances.')
                        ->modalSubmitActionLabel('Sí, eliminar todos'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
