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
    protected static ?string $navigationGroup = 'Proveedores';
    protected static ?int $navigationSort = 4;

    // Filtrar solo pagos a proveedores
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('expense_type', 'supplier_payment');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registrar Pago a Proveedor')
                    ->description('Seleccione el proveedor, el tipo de pago y los créditos que recibirá.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // 1. Selector de Proveedor
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Proveedor')
                                    ->options(function () {
                                        return Supplier::all()->mapWithKeys(function ($supplier) {
                                            $balance = number_format($supplier->balance, 2);
                                            return [$supplier->id => "{$supplier->name} - Balance: {$balance}"];
                                        });
                                    })
                                    ->required()
                                    ->native(false)
                                    ->preload()
                                    ->live()
                                    ->helperText('Seleccione el proveedor al que realizará el pago'),

                                // 2. Tipo de Pago (moneda)
                                Forms\Components\Select::make('currency')
                                    ->label('Tipo de Pago')
                                    ->options([
                                        'USDT' => '💵 USDT Cripto (Binance/Tether)',
                                        'NIO' => '🇳🇮 Transferencia Nicaragua (Córdobas NIO)',
                                        'USD' => '💲 Transferencia Nicaragua (Dólares USD)',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->helperText('Seleccione cómo realizará el pago'),
                            ]),

                        // Mostrar balance actual del proveedor
                        Forms\Components\Placeholder::make('supplier_balance_info')
                            ->label('')
                            ->content(function (Get $get) {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) {
                                    return '';
                                }

                                $supplier = Supplier::find($supplierId);
                                if (!$supplier) {
                                    return '';
                                }

                                $balance = number_format($supplier->balance, 2);

                                return new \Illuminate\Support\HtmlString(
                                    "<div class='px-4 py-2 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800'>
                                        <span class='text-green-700 dark:text-green-400 font-medium'>💰 Balance actual: {$balance} créditos</span>
                                    </div>"
                                );
                            })
                            ->visible(fn (Get $get) => !empty($get('supplier_id')))
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
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
                            ]),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referencia de Pago')
                            ->placeholder('Ej: Transfer-12345, TxID, Cheque #678')
                            ->maxLength(100)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                // 3. Monto pagado (en la moneda seleccionada)
                                Forms\Components\TextInput::make('amount')
                                    ->label(function (Get $get) {
                                        $currency = $get('currency');
                                        return match($currency) {
                                            'USDT' => 'Monto Pagado (USDT)',
                                            'NIO' => 'Monto Pagado (NIO)',
                                            'USD' => 'Monto Pagado (USD)',
                                            default => 'Monto Pagado',
                                        };
                                    })
                                    ->numeric()
                                    ->prefix(function (Get $get) {
                                        $currency = $get('currency');
                                        return match($currency) {
                                            'NIO' => 'C$',
                                            default => '$',
                                        };
                                    })
                                    ->required()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->live(onBlur: true)
                                    ->helperText('💵 Cantidad real que está pagando/depositando al proveedor'),

                                // 4. Créditos recibidos (lo que se suma al balance)
                                Forms\Components\TextInput::make('credits_received')
                                    ->label('Créditos a Recibir')
                                    ->numeric()
                                    ->prefix('⭐')
                                    ->required()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->live(onBlur: true)
                                    ->helperText('💳 Créditos que se sumarán al balance del proveedor'),
                            ]),

                        // Información del resultado
                        Forms\Components\Placeholder::make('result_info')
                            ->label('')
                            ->content(function (Get $get) {
                                $supplierId = $get('supplier_id');
                                $amount = floatval($get('amount') ?? 0);
                                $credits = floatval($get('credits_received') ?? 0);
                                $currency = $get('currency');

                                if (!$supplierId || $amount <= 0 || !$currency) {
                                    return '';
                                }

                                $supplier = Supplier::find($supplierId);
                                if (!$supplier) return '';

                                $symbol = match($currency) {
                                    'NIO' => 'C$',
                                    default => '$',
                                };
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
                            ->visible(fn (Get $get) => !empty($get('supplier_id')) && floatval($get('amount') ?? 0) > 0 && !empty($get('currency')))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Concepto / Detalle')
                            ->placeholder('Describe el motivo del pago...')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        // Campos ocultos para compatibilidad
                        Forms\Components\Hidden::make('expense_type')
                            ->default('supplier_payment')
                            ->dehydrated(),
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
                        $symbol = match($record->currency) {
                            'USDT', 'USD' => '$',
                            'NIO' => 'C$',
                            default => '$',
                        };
                        return $symbol . number_format($record->amount, 2) . ' ' . ($record->currency ?? 'USD');
                    })
                    ->description('Monto en su moneda original')
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('credits_received')
                    ->label('Créditos Recibidos')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state ?? 0, 2) . ' USD')
                    ->description('Valor en créditos USD')
                    ->color('success')
                    ->weight('bold')
                    ->icon('heroicon-o-star'),

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
