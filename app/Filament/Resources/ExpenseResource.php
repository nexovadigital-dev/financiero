<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\Currency;
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
    protected static ?string $navigationLabel = 'Pagos a Proveedores'; // <--- AQUÍ ESTABA EL ERROR
    protected static ?string $modelLabel = 'Pago / Egreso';
    protected static ?string $navigationGroup = 'Gestión';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registrar Salida de Dinero')
                    ->schema([
                        // Selector de Proveedor con Creación Rápida
                        Forms\Components\Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->label('Nombre Proveedor'),
                                Forms\Components\TextInput::make('website')
                                    ->label('Sitio Web')
                                    ->url(),
                            ])
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha del Pago')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('payment_method_id')
                            ->label('Método de Pago')
                            ->relationship('paymentMethod', 'name')
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referencia de Pago')
                            ->placeholder('Ej: Transfer-12345, Cheque #678')
                            ->maxLength(100),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('currency')
                                    ->label('Moneda')
                                    ->options(function () {
                                        return Currency::where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(function ($currency) {
                                                return [$currency->code => $currency->code . ' - ' . $currency->name];
                                            });
                                    })
                                    ->default('USD')
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $currency = Currency::where('code', $state)->first();
                                        if (!$currency) {
                                            return;
                                        }

                                        $amount = floatval($get('amount') ?? 0);

                                        if ($currency->is_base) {
                                            $set('amount_usd', $amount);
                                            $set('exchange_rate_used', 1.000000);
                                        } else {
                                            $set('exchange_rate_used', $currency->exchange_rate);
                                            if ($amount > 0) {
                                                $set('amount_usd', $currency->convertToUSD($amount));
                                            }
                                        }

                                        $set('manually_converted', false);
                                    })
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto Pagado')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $set('manually_converted', true);

                                        $currency = Currency::where('code', $get('currency'))->first();
                                        if ($currency && $state > 0) {
                                            $amountUSD = $currency->convertToUSD(floatval($state));
                                            $set('amount_usd', $amountUSD);
                                            $set('exchange_rate_used', $currency->exchange_rate);
                                        }
                                    })
                                    ->helperText('💡 Editable: Puede ajustar el monto manualmente')
                                    ->validationMessages([
                                        'min' => 'El monto debe ser mayor a 0.',
                                    ]),
                            ]),

                        Forms\Components\Placeholder::make('conversion_info')
                            ->label('Información de Conversión')
                            ->content(function (Get $get) {
                                $currency = $get('currency');
                                $amount = floatval($get('amount') ?? 0);
                                $amountUSD = floatval($get('amount_usd') ?? 0);
                                $exchangeRate = floatval($get('exchange_rate_used') ?? 0);

                                if ($currency === 'USD') {
                                    return '✓ Moneda base (USD)';
                                }

                                if ($amountUSD > 0 && $exchangeRate > 0) {
                                    return sprintf(
                                        '%s %s = $%.2f USD (Tasa: %.2f)',
                                        number_format($amount, 2),
                                        $currency,
                                        $amountUSD,
                                        $exchangeRate
                                    );
                                }

                                return 'Seleccione moneda para ver conversión';
                            }),

                        Forms\Components\Hidden::make('amount_usd')
                            ->dehydrated(),

                        Forms\Components\Hidden::make('exchange_rate_used')
                            ->dehydrated(),

                        Forms\Components\Hidden::make('manually_converted')
                            ->default(false)
                            ->dehydrated(),

                        Forms\Components\Textarea::make('description')
                            ->label('Concepto / Detalle')
                            ->placeholder('Describe el motivo del pago...')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
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
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Concepto')
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money(fn ($record) => $record->currency)
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Vía de Pago')
                    ->badge(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Proveedor'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Pago')
                    ->modalDescription('¿Está seguro que desea eliminar este pago? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Pagos Seleccionados')
                        ->modalDescription('¿Está seguro que desea eliminar los pagos seleccionados? Esta acción no se puede deshacer.')
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