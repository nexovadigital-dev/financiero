<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Ventas y Pedidos';
    protected static ?string $modelLabel = 'Venta';
    protected static ?string $navigationGroup = 'Gestión';

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'client.name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return 'Venta #' . $record->id . ' - ' . $record->client->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Total' => '$' . number_format($record->total_amount, 2),
            'Fecha' => $record->sale_date->format('d/m/Y'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECCIÓN SUPERIOR: DATOS GENERALES
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Información Básica')
                            ->schema([
                                Forms\Components\Select::make('client_id')
                                    ->label('Cliente')
                                    ->relationship('client', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required()->label('Nombre'),
                                        Forms\Components\TextInput::make('email')->email(),
                                        Forms\Components\TextInput::make('phone')->label('Teléfono'),
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('source')
                                    ->label('Origen de Venta')
                                    ->options([
                                        'store' => 'Tienda',
                                        'server' => 'Servidor',
                                    ])
                                    ->default('store')
                                    ->required(),

                                Forms\Components\DateTimePicker::make('sale_date')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required(),

                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'pending' => 'Pendiente',
                                        'completed' => 'Completado',
                                        'cancelled' => 'Cancelado',
                                    ])
                                    ->default('completed')
                                    ->required(),
                            ])->columns(2),
                    ])->columnSpan(2),

                // SECCIÓN LATERAL: TOTALES Y PAGO
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pago')
                            ->schema([
                                Forms\Components\Select::make('payment_method_id')
                                    ->label('Método de Pago')
                                    ->relationship('paymentMethod', 'name')
                                    ->required(),

                                Forms\Components\TextInput::make('payment_reference')
                                    ->label('Referencia de Pago')
                                    ->placeholder('Ej: Transfer-12345, Cheque #678')
                                    ->maxLength(100),

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
                                        self::convertCurrency($get, $set, $state);
                                    })
                                    ->required(),

                                Forms\Components\TextInput::make('total_amount')
                                    ->label('TOTAL A PAGAR')
                                    ->prefix('$')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        // Si el usuario edita manualmente, marcar como convertido manualmente
                                        $set('manually_converted', true);

                                        // Recalcular USD basado en el valor editado
                                        $currency = Currency::where('code', $get('currency'))->first();
                                        if ($currency) {
                                            $amountUSD = $currency->convertToUSD(floatval($state));
                                            $set('amount_usd', $amountUSD);
                                            $set('exchange_rate_used', $currency->exchange_rate);
                                        }
                                    })
                                    ->extraInputAttributes(['style' => 'font-size: 1.5rem; font-weight: bold; color: #7cbd2b; text-align: right;'])
                                    ->helperText('💡 Editable: Puede ajustar el monto manualmente')
                                    ->required(),

                                Forms\Components\Placeholder::make('conversion_info')
                                    ->label('Información de Conversión')
                                    ->content(function (Get $get) {
                                        $currency = $get('currency');
                                        $totalAmount = floatval($get('total_amount') ?? 0);
                                        $amountUSD = floatval($get('amount_usd') ?? 0);
                                        $exchangeRate = floatval($get('exchange_rate_used') ?? 0);

                                        if ($currency === 'USD') {
                                            return '✓ Moneda base (USD)';
                                        }

                                        if ($amountUSD > 0 && $exchangeRate > 0) {
                                            return sprintf(
                                                '%s %s = $%.2f USD (Tasa: %.2f)',
                                                number_format($totalAmount, 2),
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
                            ]),
                    ])->columnSpan(1),

                // SECCIÓN INFERIOR: CARRITO DE PRODUCTOS
                Forms\Components\Section::make('Productos')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->label('Producto')
                                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->reactive() // Escucha cambios
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                // 1. Busca el precio del producto
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('unit_price', $product->price);
                                                    // 2. Calcula subtotal (Precio x Cantidad)
                                                    $qty = $get('quantity') ?? 1;
                                                    $set('total_price', $product->price * $qty);
                                                }
                                            })
                                            ->columnSpan(2) // Ocupa más espacio
                                            ->required(),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Cant.')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->live(onBlur: true) // Actualiza al salir del campo
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $price = $get('unit_price') ?? 0;
                                                $set('total_price', $price * $state);
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Precio Unit. (USD)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->live(onBlur: true) // PERMITE EDITAR EL PRECIO
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $qty = $get('quantity') ?? 1;
                                                $set('total_price', $state * $qty);
                                            })
                                            ->helperText(function (Get $get) {
                                                $currency = $get('../../currency');
                                                $price = floatval($get('unit_price') ?? 0);

                                                if ($currency && $currency !== 'USD' && $price > 0) {
                                                    $currencyModel = \App\Models\Currency::where('code', $currency)->first();
                                                    if ($currencyModel) {
                                                        $converted = $currencyModel->convertFromUSD($price);
                                                        return "≈ " . number_format($converted, 2) . " " . $currency;
                                                    }
                                                }

                                                return 'Precio base en USD';
                                            })
                                            ->columnSpan(1),
                                            
                                        // Campo oculto para guardar el subtotal de la línea
                                        Forms\Components\Hidden::make('total_price')
                                            ->dehydrated(), 
                                    ]),
                            ])
                            ->live() // IMPORTANTE: Escucha cambios en agregar/borrar items
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->after(fn(Get $get, Set $set) => self::updateTotals($get, $set)),
                            ),
                    ])->columnSpanFull(),
            ])->columns(3);
    }

    // Función auxiliar para recalcular el TOTAL GLOBAL
    public static function updateTotals(Get $get, Set $set): void
    {
        $items = $get('items'); // Obtiene todos los items del repeater
        $sum = 0;

        if ($items) {
            foreach ($items as $item) {
                // Sumamos (Precio Unitario * Cantidad)
                $price = floatval($item['unit_price'] ?? 0);
                $qty = intval($item['quantity'] ?? 1);
                $sum += ($price * $qty);
            }
        }

        $set('total_amount', $sum);

        // Aplicar conversión de moneda automáticamente
        $currency = $get('currency');
        if ($currency) {
            self::convertCurrency($get, $set, $currency);
        }
    }

    // Función para convertir moneda automáticamente
    public static function convertCurrency(Get $get, Set $set, $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)->first();

        if (!$currency) {
            return;
        }

        $totalAmount = floatval($get('total_amount') ?? 0);

        if ($currency->is_base) {
            // Si es USD (moneda base), no hay conversión
            $set('amount_usd', $totalAmount);
            $set('exchange_rate_used', 1.000000);
            $set('manually_converted', false);
        } else {
            // Convertir de USD a la moneda seleccionada
            // Los productos están en USD por defecto, entonces convertimos a la moneda destino
            $convertedAmount = $currency->convertFromUSD($totalAmount);
            $set('total_amount', $convertedAmount);
            $set('amount_usd', $totalAmount); // Guardamos el original en USD
            $set('exchange_rate_used', $currency->exchange_rate);
            $set('manually_converted', false);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('# Orden')
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->colors([
                        'success' => 'store',   // Verde para tienda
                        'warning' => 'server',  // Amarillo para servidor
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'store' => 'Tienda',
                        'server' => 'Servidor',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable(),

                // Muestra los productos en lista
                Tables\Columns\TextColumn::make('items.product.name')
                    ->label('Artículos')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->bulleted(),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Pago')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}