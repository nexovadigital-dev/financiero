<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Models\Product;
use App\Models\PricePackage;
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

                                Forms\Components\Select::make('price_package_id')
                                    ->label('📦 Paquete de Precios')
                                    ->options(function () {
                                        return PricePackage::active()->ordered()->get()->mapWithKeys(function ($package) {
                                            $symbol = $package->currency === 'NIO' ? 'C$' : '$';
                                            $label = $package->name . ' (' . $symbol . ' ' . $package->currency . ')';
                                            return [$package->id => $label];
                                        });
                                    })
                                    ->default(fn () => PricePackage::active()->ordered()->first()?->id)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($state) {
                                            $package = PricePackage::find($state);
                                            if ($package) {
                                                // Si el paquete es NIO, forzar la moneda a NIO
                                                if ($package->isNIO()) {
                                                    $set('currency', 'NIO');
                                                    // Limpiar método de pago para que seleccione uno NIO
                                                    $set('payment_method_id', null);

                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('Paquete en Córdobas (NIO)')
                                                        ->body('Este paquete usa precios en córdobas. Se filtrarán los métodos de pago compatibles.')
                                                        ->send();
                                                }
                                                // Recalcular precios de los items
                                                self::recalculateItemPricesForPackage($get, $set, $state);
                                            }
                                        }
                                    })
                                    ->helperText(fn (Get $get) =>
                                        ($pkg = PricePackage::find($get('price_package_id')))
                                            ? ($pkg->isNIO()
                                                ? '⚠️ Paquete en CÓRDOBAS - Solo métodos de pago NIO disponibles'
                                                : '✓ Paquete en USD')
                                            : 'Seleccione el paquete de precios'
                                    )
                                    ->columnSpanFull(),

                                Forms\Components\Checkbox::make('without_supplier')
                                    ->label('Sin Proveedor (venta como INGRESO)')
                                    ->helperText('Si marca esta opción, la venta de créditos NO se debitará del proveedor.')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $set('supplier_id', null); // Limpiar proveedor
                                        }
                                    })
                                    ->visible(fn (Get $get) => self::isServerCreditsPayment($get('payment_method_id')))
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('supplier_id')
                                    ->label(fn (Get $get) => self::isServerCreditsPayment($get('payment_method_id'))
                                        ? 'Proveedor (OBLIGATORIO para Créditos Servidor)'
                                        : 'Proveedor (Referencia Interna)')
                                    ->relationship('supplier', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Seleccionar proveedor')
                                    ->helperText(fn (Get $get) => self::isServerCreditsPayment($get('payment_method_id'))
                                        ? '⚠️ Debe seleccionar el proveedor del cual se debitará el crédito.'
                                        : 'Vincular esta venta con un proveedor para contabilidad interna.')
                                    ->required(fn (Get $get) => self::isServerCreditsPayment($get('payment_method_id')) && !$get('without_supplier'))
                                    ->visible(fn (Get $get) => !$get('without_supplier'))
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        // Cuando cambie el proveedor, recalcular los precios base de todos los items
                                        self::recalculateItemPrices($get, $set, $state);
                                    }),

                                // Mostrar balance disponible del proveedor
                                Forms\Components\Placeholder::make('supplier_balance_info')
                                    ->label('💰 Balance Disponible')
                                    ->content(function (Get $get) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) {
                                            return 'Seleccione un proveedor';
                                        }

                                        $supplier = \App\Models\Supplier::find($supplierId);
                                        if (!$supplier) {
                                            return 'Proveedor no encontrado';
                                        }

                                        $balance = $supplier->balance ?? 0;
                                        $color = $balance > 0 ? 'green' : 'red';

                                        return new \Illuminate\Support\HtmlString(
                                            "<span style='font-size: 1.2rem; font-weight: bold; color: {$color};'>\$" .
                                            number_format($balance, 2) . " USD</span>"
                                        );
                                    })
                                    ->visible(fn (Get $get) => self::isServerCreditsPayment($get('payment_method_id')) && $get('supplier_id') && !$get('without_supplier')),

                                // Mostrar costo base total que se debitará
                                Forms\Components\Placeholder::make('base_cost_info')
                                    ->label('📊 Costo Base (se debitará)')
                                    ->content(function (Get $get) {
                                        $items = $get('items') ?? [];
                                        $totalBaseCost = 0;

                                        foreach ($items as $item) {
                                            $basePrice = floatval($item['base_price'] ?? 0);
                                            $qty = intval($item['quantity'] ?? 1);
                                            $totalBaseCost += ($basePrice * $qty);
                                        }

                                        $supplierId = $get('supplier_id');
                                        $supplier = $supplierId ? \App\Models\Supplier::find($supplierId) : null;
                                        $balance = $supplier ? ($supplier->balance ?? 0) : 0;

                                        $hasEnough = $balance >= $totalBaseCost;
                                        $color = $hasEnough ? 'orange' : 'red';
                                        $icon = $hasEnough ? '✓' : '⚠️';

                                        $html = "<span style='font-size: 1.1rem; font-weight: bold; color: {$color};'>{$icon} \$" .
                                            number_format($totalBaseCost, 2) . " USD</span>";

                                        if (!$hasEnough && $totalBaseCost > 0) {
                                            $html .= "<br><span style='color: red; font-weight: bold;'>❌ Balance insuficiente</span>";
                                        }

                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->visible(fn (Get $get) => self::isServerCreditsPayment($get('payment_method_id')) && $get('supplier_id') && !$get('without_supplier')),

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
                                    ->options(function (Get $get) {
                                        $packageId = $get('price_package_id');
                                        $package = $packageId ? PricePackage::find($packageId) : null;

                                        $query = \App\Models\PaymentMethod::where('is_active', true);

                                        // Si el paquete es NIO, mostrar métodos NIO + Créditos Servidor (siempre disponible)
                                        if ($package && $package->isNIO()) {
                                            $query->where(function ($q) {
                                                $q->where('currency', 'NIO')
                                                  ->orWhere('name', 'Créditos Servidor');
                                            });
                                        }

                                        return $query->pluck('name', 'id');
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        // Cambiar moneda automáticamente según método de pago
                                        if ($state) {
                                            $paymentMethod = \App\Models\PaymentMethod::find($state);
                                            if ($paymentMethod) {
                                                $set('currency', $paymentMethod->currency);
                                                // Aplicar conversión de moneda
                                                self::convertCurrency($get, $set, $paymentMethod->currency);
                                            }
                                        }
                                    })
                                    ->helperText(function (Get $get) {
                                        $packageId = $get('price_package_id');
                                        $package = $packageId ? PricePackage::find($packageId) : null;
                                        if ($package && $package->isNIO()) {
                                            return '⚠️ Filtrado: Solo métodos de pago en córdobas (NIO)';
                                        }
                                        return null;
                                    })
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
                                    ->disabled() // No editable - se define automáticamente por el método de pago
                                    ->dehydrated() // Pero sí se guarda en BD
                                    ->helperText('✓ Moneda del método de pago seleccionado')
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        self::convertCurrency($get, $set, $state);
                                    })
                                    ->required(),

                                Forms\Components\TextInput::make('total_amount')
                                    ->label('TOTAL A PAGAR')
                                    ->prefix(function (Get $get) {
                                        $currency = $get('currency');
                                        return match($currency) {
                                            'NIO' => 'C$',
                                            'USD' => '$',
                                            'USDT' => '$',
                                            default => '$',
                                        };
                                    })
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
                            ->minItems(1)
                            ->label('Productos de la Venta')
                            ->helperText('⚠️ Debe agregar al menos un producto a la venta')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->label('Producto')
                                            ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->reactive() // Escucha cambios
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                // 1. Busca el producto
                                                $product = Product::find($state);
                                                if ($product) {
                                                    // 2. Obtener el paquete de precios y proveedor seleccionados
                                                    $packageId = $get('../../price_package_id');
                                                    $supplierId = $get('../../supplier_id');

                                                    // 3. Determinar el precio según el paquete
                                                    $packagePrice = null;
                                                    if ($packageId) {
                                                        $packagePrice = $product->getPriceForPackage($packageId);

                                                        // VALIDACIÓN CRÍTICA: Si el paquete está seleccionado pero el precio no está configurado
                                                        if ($packagePrice <= 0) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->warning()
                                                                ->title('⚠️ Precio no configurado')
                                                                ->body("El producto '{$product->name}' no tiene precio configurado para el paquete seleccionado. Usando precio base como referencia.")
                                                                ->send();

                                                            // Usar precio base del producto como fallback
                                                            $packagePrice = $product->price > 0 ? $product->price : 0;
                                                        }
                                                    } else {
                                                        $packagePrice = $product->price;
                                                    }

                                                    // 4. Obtener precio base según el PROVEEDOR seleccionado
                                                    $basePrice = $product->getBasePriceForSupplier($supplierId);
                                                    $basePriceNio = $product->getBasePriceNioForSupplier($supplierId);

                                                    // 5. Guardar precios
                                                    $set('base_price', $basePrice); // Precio que se debita del proveedor (USD)
                                                    $set('base_price_nio', $basePriceNio ?? 0); // Precio en NIO para reportes
                                                    $set('package_price', $packagePrice); // Precio que se cobra al cliente
                                                    $set('unit_price', $packagePrice); // Para compatibilidad

                                                    // 6. Calcula subtotal (Precio del paquete x Cantidad)
                                                    $qty = $get('quantity') ?? 1;
                                                    $set('total_price', $packagePrice * $qty);
                                                }
                                            })
                                            ->columnSpan(2) // Ocupa más espacio
                                            ->required(),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Cant.')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        // Precio Base USD (editable)
                                        Forms\Components\TextInput::make('base_price')
                                            ->label('Costo USD')
                                            ->numeric()
                                            ->prefix('$')
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->dehydrated()
                                            ->extraInputAttributes(['style' => 'background-color: #fef3c7; color: #92400e; font-weight: bold;'])
                                            ->columnSpan(1),

                                        // Precio Base NIO (editable, solo visible cuando moneda es NIO)
                                        Forms\Components\TextInput::make('base_price_nio')
                                            ->label('Costo NIO')
                                            ->numeric()
                                            ->prefix('C$')
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->dehydrated()
                                            ->extraInputAttributes(['style' => 'background-color: #dbeafe; color: #1e40af; font-weight: bold;'])
                                            ->visible(fn (Get $get) => $get('../../currency') === 'NIO')
                                            ->columnSpan(1),

                                        // Campos ocultos para guardar precios
                                        Forms\Components\Hidden::make('total_price')
                                            ->dehydrated(),
                                        Forms\Components\Hidden::make('unit_price')
                                            ->dehydrated(),
                                        Forms\Components\Hidden::make('package_price')
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
                    ->label('#')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->colors([
                        'success' => 'store',
                        'warning' => 'server',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'store' => '🏪',
                        'server' => '🖥️',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Pago')
                    ->badge()
                    ->color('gray')
                    ->limit(20),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(function ($record) {
                        $currency = $record->currency ?? 'USD';
                        $symbol = match($currency) {
                            'NIO' => 'C$',
                            'USD' => '$',
                            'USDT' => '$',
                            default => '$',
                        };
                        return $symbol . number_format($record->total_amount, 2) . ' ' . $currency;
                    })
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('refunded_at')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->isRefunded() ? 'REEMB.' :
                        ($record->isProviderCredit() ? 'Crédito' : 'OK')
                    )
                    ->color(fn ($record) =>
                        $record->isRefunded() ? 'danger' :
                        ($record->isProviderCredit() ? 'warning' : 'success')
                    )
                    ->icon(fn ($record) =>
                        $record->isRefunded() ? 'heroicon-o-arrow-uturn-left' :
                        ($record->isProviderCredit() ? 'heroicon-o-credit-card' : 'heroicon-o-check-circle')
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        'store' => '🏪 Tienda',
                        'server' => '🖥️ Servidor',
                    ])
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('payment_method_id')
                    ->label('Método de Pago')
                    ->relationship('paymentMethod', 'name')
                    ->placeholder('Todos'),

                Tables\Filters\Filter::make('refunded')
                    ->label('Solo Reembolsadas')
                    ->query(fn ($query) => $query->whereNotNull('refunded_at')),

                Tables\Filters\Filter::make('not_refunded')
                    ->label('Sin Reembolsar')
                    ->query(fn ($query) => $query->whereNull('refunded_at'))
                    ->default(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn ($record) => $record->isRefunded() ? 'opacity-50 line-through' : null)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Venta')
                    ->modalDescription(fn ($record) =>
                        $record->isProviderCredit() && !$record->without_supplier && !$record->isRefunded()
                            ? '⚠️ ADVERTENCIA: Esta es una venta de créditos. Al eliminarla, se restaurará el balance del proveedor.'
                            : '¿Está seguro que desea eliminar esta venta?'
                    )
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->before(function ($record) {
                        // Si es una venta de créditos activa, restaurar balance antes de eliminar
                        if ($record->isProviderCredit() && !$record->without_supplier && !$record->isRefunded() && $record->supplier) {
                            $totalBaseCost = $record->items->sum(function ($item) {
                                return ($item->base_price ?? 0) * $item->quantity;
                            });
                            $amountToRefund = $totalBaseCost > 0 ? $totalBaseCost : $record->amount_usd;

                            $record->supplier->addToBalance($amountToRefund);

                            \Log::info('💰 Balance restaurado por eliminación de venta', [
                                'sale_id' => $record->id,
                                'supplier' => $record->supplier->name,
                                'amount_restored' => $amountToRefund,
                            ]);
                        }
                    }),
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

    /**
     * Verificar si el método de pago seleccionado es "Créditos Servidor"
     */
    private static function isServerCreditsPayment($paymentMethodId): bool
    {
        if (!$paymentMethodId) {
            return false;
        }

        $paymentMethod = \App\Models\PaymentMethod::find($paymentMethodId);
        return $paymentMethod && $paymentMethod->name === 'Créditos Servidor';
    }

    /**
     * Recalcular los precios base de todos los items cuando cambia el proveedor
     */
    public static function recalculateItemPrices(Get $get, Set $set, $supplierId): void
    {
        $items = $get('items');

        if (!$items || !is_array($items)) {
            return;
        }

        $updatedItems = [];
        foreach ($items as $key => $item) {
            $productId = $item['product_id'] ?? null;

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    // Obtener precio base según el nuevo proveedor
                    $basePrice = $product->getBasePriceForSupplier($supplierId);
                    $basePriceNio = $product->getBasePriceNioForSupplier($supplierId);

                    $item['base_price'] = $basePrice;
                    $item['base_price_nio'] = $basePriceNio;
                }
            }

            $updatedItems[$key] = $item;
        }

        $set('items', $updatedItems);

        // Notificar al usuario
        if ($supplierId) {
            $supplier = \App\Models\Supplier::find($supplierId);
            if ($supplier) {
                \Filament\Notifications\Notification::make()
                    ->info()
                    ->title('Precios actualizados')
                    ->body("Los precios base se han actualizado según el proveedor: {$supplier->name}")
                    ->send();
            }
        }
    }

    /**
     * Recalcular los precios de venta cuando cambia el paquete de precios
     */
    public static function recalculateItemPricesForPackage(Get $get, Set $set, $packageId): void
    {
        $items = $get('items');

        if (!$items || !is_array($items) || !$packageId) {
            return;
        }

        $package = PricePackage::find($packageId);
        if (!$package) {
            return;
        }

        $updatedItems = [];
        $totalVenta = 0;

        foreach ($items as $key => $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = floatval($item['quantity'] ?? 1);

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    // Obtener precio del paquete
                    $packagePrice = $product->getPriceForPackage($packageId);

                    // Si no hay precio para el paquete, usar precio base
                    if ($packagePrice <= 0) {
                        $packagePrice = $product->price > 0 ? $product->price : 0;
                    }

                    $item['package_price'] = $packagePrice;
                    $item['unit_price'] = $packagePrice;
                    $item['total_price'] = $packagePrice * $quantity;

                    $totalVenta += $item['total_price'];
                }
            }

            $updatedItems[$key] = $item;
        }

        $set('items', $updatedItems);

        // Actualizar total de la venta
        if ($totalVenta > 0) {
            $set('total_amount', $totalVenta);

            // Si es paquete USD, actualizar amount_usd
            if ($package->isUSD()) {
                $set('amount_usd', $totalVenta);
            } else {
                // Si es NIO, convertir a USD
                $currency = Currency::where('code', 'NIO')->first();
                if ($currency) {
                    $amountUsd = $currency->convertToUSD($totalVenta);
                    $set('amount_usd', $amountUsd);
                    $set('exchange_rate_used', $currency->exchange_rate);
                }
            }
        }
    }
}