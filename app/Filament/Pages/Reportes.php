<?php

namespace App\Filament\Pages;

use App\Models\Currency;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class Reportes extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Reportes Financieros';

    protected static ?string $title = 'Reportes Financieros';

    protected static ?string $navigationGroup = 'Gestión';

    protected static string $view = 'filament.pages.reportes';

    public ?array $filters = [
        'start_date' => null,
        'end_date' => null,
        'currency' => 'ALL',
        'source' => 'all',
        'payment_method_id' => 'all',
        'client_id' => null,
        'product_type' => 'all',
    ];

    public string $activeTab = 'ALL';

    public function mount(): void
    {
        $this->activeTab = 'ALL';
        $this->filters['currency'] = 'ALL';
        $this->filters['start_date'] = now()->startOfMonth();
        $this->filters['end_date'] = now()->endOfDay();
    }

    public function updatedActiveTab($value): void
    {
        $this->filters['currency'] = $value;
        $this->resetTable();
    }

    // Método para aplicar filtros manualmente
    public function applyFilters(): void
    {
        $this->resetTable();
    }

    // Helper para parsear fechas correctamente
    protected function parseDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof \Carbon\Carbon) {
            return $date->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtros de Reporte')
                    ->description('Selecciona los filtros y presiona "Aplicar Filtros".')
                    ->schema([
                        // Fechas - 2 columnas
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Fecha Inicio')
                                    ->required()
                                    ->default(now()->startOfMonth())
                                    ->native(false)
                                    ->closeOnDateSelection(),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Fecha Fin')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->closeOnDateSelection(),
                            ]),

                        // Filtros principales - 3 columnas en desktop, 2 en tablet, 1 en móvil
                        Forms\Components\Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                Forms\Components\Select::make('source')
                                    ->label('Origen de Venta')
                                    ->options([
                                        'all' => 'Todos los orígenes',
                                        'store' => 'Tienda',
                                        'server' => 'Servidor',
                                    ])
                                    ->default('all'),

                                Forms\Components\Select::make('product_type')
                                    ->label('Tipo de Producto')
                                    ->options([
                                        'all' => 'Todos los tipos',
                                        'digital_product' => 'Artículos',
                                        'service' => 'Servicios',
                                        'server_credit' => 'Créditos',
                                    ])
                                    ->default('all'),

                                Forms\Components\Select::make('payment_method_id')
                                    ->label('Método de Pago')
                                    ->options(function () {
                                        $methods = \App\Models\PaymentMethod::pluck('name', 'id')->toArray();
                                        return ['all' => 'Todos los métodos'] + $methods;
                                    })
                                    ->default('all'),
                            ]),

                        // Búsqueda de cliente con búsqueda async
                        Forms\Components\Select::make('client_id')
                            ->label('Cliente')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                if (strlen($search) < 2) {
                                    return [];
                                }

                                return \App\Models\Client::query()
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%");
                                    })
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string =>
                                \App\Models\Client::find($value)?->name
                            )
                            ->placeholder('Buscar cliente por nombre, email o teléfono...')
                            ->nullable()
                            ->default(null)
                            ->helperText('Escribe para buscar entre 6,000+ clientes'),

                        // Botón para aplicar filtros
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('aplicar_filtros')
                                ->label('Aplicar Filtros')
                                ->icon('heroicon-m-funnel')
                                ->color('primary')
                                ->size('lg')
                                ->action('applyFilters'),
                        ])->columnSpanFull(),
                    ])
                    ->collapsed(false)
                    ->collapsible(),
            ])
            ->statePath('filters');
    }

    /**
     * Obtener estadísticas financieras completas
     * - Ingresos: ventas con métodos de pago que NO sean "Créditos Servidor"
     * - Egresos por Créditos: ventas con método "Créditos Servidor" (debitan del proveedor)
     * - Gastos/Inversiones: pagos a proveedores (tabla expenses)
     */
    public function getFinancialStats(): array
    {
        $startDate = $this->parseDate($this->filters['start_date']) ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->parseDate($this->filters['end_date']) ?? now()->format('Y-m-d');
        $currency = $this->activeTab;

        // Obtener el ID del método de pago "Créditos Servidor"
        $creditosServidor = PaymentMethod::where('name', 'Créditos Servidor')->first();
        $creditosServidorId = $creditosServidor?->id;

        // Query base para ventas
        $baseQuery = Sale::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate);

        // Aplicar filtro de moneda si no es "ALL"
        if ($currency !== 'ALL') {
            $baseQuery->where('currency', $currency);
        }

        // 1. INGRESOS REALES: Ventas SIN método "Créditos Servidor"
        $ingresosQuery = clone $baseQuery;
        if ($creditosServidorId) {
            $ingresosQuery->where('payment_method_id', '!=', $creditosServidorId);
        }

        if ($currency === 'ALL') {
            $totalIngresos = $ingresosQuery->sum('amount_usd');
        } else {
            $totalIngresos = $ingresosQuery->sum('total_amount');
        }
        $cantidadVentasIngresos = $ingresosQuery->count();

        // 2. USO DE CRÉDITOS: Total de base_price (USD) gastado de TODAS las ventas
        // Representa el costo real de los productos vendidos - SIEMPRE EN USD
        $egresosQuery = Sale::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->with('items');

        // NO filtrar por moneda - siempre sumar todo en USD
        $cantidadVentasCreditos = $egresosQuery->count();
        // Siempre sumar base_price en USD (costo real del proveedor)
        $totalEgresos = $egresosQuery->get()->sum(function ($sale) {
            return $sale->items->sum(function ($item) {
                return ($item->base_price ?? 0) * $item->quantity;
            });
        });

        // 3. INVERSIONES: Pagos a proveedores - SIEMPRE EN USD
        // Usar credits_received como valor real en USD (los créditos = valor USD)
        $gastosQuery = Expense::query()
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate);

        $cantidadGastos = $gastosQuery->count();

        // Sumar credits_received (representa el valor real en USD depositado)
        $totalGastos = $gastosQuery->sum('credits_received');

        // 4. COSTO DE VENTAS - SIEMPRE EN USD (base_price)
        $costoVentasQuery = Sale::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->with('items');

        if ($currency !== 'ALL') {
            $costoVentasQuery->where('currency', $currency);
        }

        // Siempre sumar base_price en USD
        $costoVentas = $costoVentasQuery->get()->sum(function ($sale) {
            return $sale->items->sum(function ($item) {
                return ($item->base_price ?? 0) * $item->quantity;
            });
        });

        // 5. BALANCE NETO - SIEMPRE EN USD
        // Usar amount_usd de ingresos para comparar con inversiones (también en USD)
        $ingresosUsdQuery = Sale::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate);

        if ($creditosServidorId) {
            $ingresosUsdQuery->where('payment_method_id', '!=', $creditosServidorId);
        }

        $ingresosUsd = $ingresosUsdQuery->sum('amount_usd');
        $balanceNeto = $ingresosUsd - $totalGastos;

        // 6. GANANCIA BRUTA = Ingresos - Costo de Ventas
        $gananciaBruta = $totalIngresos - $costoVentas;

        // Total de ventas (todas)
        $totalVentasQuery = clone $baseQuery;
        $totalVentas = $totalVentasQuery->count();

        return [
            'ingresos' => $totalIngresos,
            'ingresos_count' => $cantidadVentasIngresos,
            'egresos_creditos' => $totalEgresos,
            'egresos_count' => $cantidadVentasCreditos,
            'gastos' => $totalGastos,
            'gastos_count' => $cantidadGastos,
            'costo_ventas' => $costoVentas,
            'ganancia_bruta' => $gananciaBruta,
            'balance_neto' => $balanceNeto,
            'total_ventas' => $totalVentas,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Sale::query())
            ->modifyQueryUsing(function (Builder $query) {
                $data = $this->filters;

                $query->with(['client', 'paymentMethod', 'items.product']);
                $query->whereNull('refunded_at');
                $query->where('status', 'completed');

                // Solo filtrar por moneda si NO es "ALL" (todas)
                if ($this->activeTab !== 'ALL') {
                    $query->where('currency', $this->activeTab);
                }

                // Aplicar filtros de fecha
                $startDate = $this->parseDate($data['start_date']);
                $endDate = $this->parseDate($data['end_date']);

                if ($startDate) {
                    $query->whereDate('sale_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->whereDate('sale_date', '<=', $endDate);
                }
                if (! empty($data['source']) && $data['source'] !== 'all') {
                    $query->where('source', $data['source']);
                }
                if (! empty($data['payment_method_id']) && $data['payment_method_id'] !== 'all') {
                    $query->where('payment_method_id', $data['payment_method_id']);
                }
                if (! empty($data['client_id'])) {
                    $query->where('client_id', $data['client_id']);
                }
                if (! empty($data['product_type']) && $data['product_type'] !== 'all') {
                    $query->whereHas('items.product', function ($q) use ($data) {
                        $q->where('type', $data['product_type']);
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->weight('bold')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('sale_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->client?->name),

                Tables\Columns\TextColumn::make('items')
                    ->label('Productos')
                    ->formatStateUsing(fn ($record) => $record->items
                        ->map(fn ($item) => $item->product_name ?? $item->product?->name ?? 'Producto eliminado')
                        ->join(', ')
                    )
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->items
                        ->map(fn ($item) => ($item->product_name ?? $item->product?->name ?? 'Eliminado') . ' x' . $item->quantity)
                        ->join("\n")
                    ),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->colors(['success' => 'store', 'warning' => 'server'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'store' => 'Tienda',
                        'server' => 'Servidor',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Método')
                    ->badge()
                    ->color('info')
                    ->limit(15),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'USD' => 'success',
                        'NIO' => 'warning',
                        'USDT' => 'info',
                        default => 'gray',
                    })
                    ->visible(fn () => $this->activeTab === 'ALL'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(function ($record) {
                        $currency = $record->currency ?? 'USD';
                        $symbol = match($currency) {
                            'NIO' => 'C$',
                            'USD' => '$',
                            'USDT' => '$',
                            default => $currency . ' ',
                        };
                        return $symbol . number_format($record->total_amount, 2) . ' ' . $currency;
                    })
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('base_cost')
                    ->label('Costo')
                    ->formatStateUsing(function ($record) {
                        $currency = $record->currency ?? 'USD';
                        $symbol = match($currency) {
                            'NIO' => 'C$',
                            'USD' => '$',
                            'USDT' => '$',
                            'COP' => 'COP ',
                            default => $currency . ' ',
                        };

                        // Detectar si el método de pago es de Nicaragua USD
                        $isNicaraguaUsd = false;
                        if ($record->paymentMethod) {
                            $isNicaraguaUsd = str_contains(strtolower($record->paymentMethod->name), 'nicaragua');
                        }

                        $cost = $record->items->sum(function ($item) use ($currency, $record, $isNicaraguaUsd) {
                            // Si es método de pago Nicaragua USD, usar base_price_usd_nic
                            if ($isNicaraguaUsd && ($item->base_price_usd_nic ?? 0) > 0) {
                                return $item->base_price_usd_nic * $item->quantity;
                            }
                            // Si es USD o USDT (y NO es Nicaragua), usar base_price
                            if (in_array($currency, ['USD', 'USDT'])) {
                                return ($item->base_price ?? 0) * $item->quantity;
                            }
                            // Para NIO, preferir base_price_nio si existe
                            if ($currency === 'NIO' && ($item->base_price_nio ?? 0) > 0) {
                                return $item->base_price_nio * $item->quantity;
                            }
                            // Para cualquier otra moneda, convertir base_price (USD) usando tasa de cambio
                            $basePriceUsd = $item->base_price ?? 0;
                            $exchangeRate = $record->exchange_rate_used ?? 1;
                            return ($basePriceUsd * $exchangeRate) * $item->quantity;
                        });
                        return $symbol . number_format($cost, 2) . ' ' . $currency;
                    })
                    ->color('warning')
                    ->getStateUsing(function ($record) {
                        $currency = $record->currency ?? 'USD';

                        // Detectar si el método de pago es de Nicaragua USD
                        $isNicaraguaUsd = false;
                        if ($record->paymentMethod) {
                            $isNicaraguaUsd = str_contains(strtolower($record->paymentMethod->name), 'nicaragua');
                        }

                        return $record->items->sum(function ($item) use ($currency, $record, $isNicaraguaUsd) {
                            // Si es método de pago Nicaragua USD, usar base_price_usd_nic
                            if ($isNicaraguaUsd && ($item->base_price_usd_nic ?? 0) > 0) {
                                return $item->base_price_usd_nic * $item->quantity;
                            }
                            if (in_array($currency, ['USD', 'USDT'])) {
                                return ($item->base_price ?? 0) * $item->quantity;
                            }
                            if ($currency === 'NIO' && ($item->base_price_nio ?? 0) > 0) {
                                return $item->base_price_nio * $item->quantity;
                            }
                            $basePriceUsd = $item->base_price ?? 0;
                            $exchangeRate = $record->exchange_rate_used ?? 1;
                            return ($basePriceUsd * $exchangeRate) * $item->quantity;
                        });
                    }),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Ganancia')
                    ->formatStateUsing(function ($record) {
                        $currency = $record->currency ?? 'USD';
                        $symbol = match($currency) {
                            'NIO' => 'C$',
                            'USD' => '$',
                            'USDT' => '$',
                            'COP' => 'COP ',
                            default => $currency . ' ',
                        };

                        // Detectar si el método de pago es de Nicaragua USD
                        $isNicaraguaUsd = false;
                        if ($record->paymentMethod) {
                            $isNicaraguaUsd = str_contains(strtolower($record->paymentMethod->name), 'nicaragua');
                        }

                        $total = $record->total_amount;
                        $cost = $record->items->sum(function ($item) use ($currency, $record, $isNicaraguaUsd) {
                            // Si es método de pago Nicaragua USD, usar base_price_usd_nic
                            if ($isNicaraguaUsd && ($item->base_price_usd_nic ?? 0) > 0) {
                                return $item->base_price_usd_nic * $item->quantity;
                            }
                            if (in_array($currency, ['USD', 'USDT'])) {
                                return ($item->base_price ?? 0) * $item->quantity;
                            }
                            if ($currency === 'NIO' && ($item->base_price_nio ?? 0) > 0) {
                                return $item->base_price_nio * $item->quantity;
                            }
                            $basePriceUsd = $item->base_price ?? 0;
                            $exchangeRate = $record->exchange_rate_used ?? 1;
                            return ($basePriceUsd * $exchangeRate) * $item->quantity;
                        });
                        $profit = $total - $cost;
                        return $symbol . number_format($profit, 2) . ' ' . $currency;
                    })
                    ->weight('bold')
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        $currency = $record->currency ?? 'USD';

                        // Detectar si el método de pago es de Nicaragua USD
                        $isNicaraguaUsd = false;
                        if ($record->paymentMethod) {
                            $isNicaraguaUsd = str_contains(strtolower($record->paymentMethod->name), 'nicaragua');
                        }

                        $total = $record->total_amount;
                        $cost = $record->items->sum(function ($item) use ($currency, $record, $isNicaraguaUsd) {
                            // Si es método de pago Nicaragua USD, usar base_price_usd_nic
                            if ($isNicaraguaUsd && ($item->base_price_usd_nic ?? 0) > 0) {
                                return $item->base_price_usd_nic * $item->quantity;
                            }
                            if (in_array($currency, ['USD', 'USDT'])) {
                                return ($item->base_price ?? 0) * $item->quantity;
                            }
                            if ($currency === 'NIO' && ($item->base_price_nio ?? 0) > 0) {
                                return $item->base_price_nio * $item->quantity;
                            }
                            $basePriceUsd = $item->base_price ?? 0;
                            $exchangeRate = $record->exchange_rate_used ?? 1;
                            return ($basePriceUsd * $exchangeRate) * $item->quantity;
                        });
                        return $total - $cost;
                    }),

            ])
            ->defaultSort('sale_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Excel')
                    ->color('success')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->exports([
                        ExcelExport::make('completo')
                            ->label('Reporte Completo')
                            ->fromTable()
                            ->withFilename(fn () => 'Reporte_'.$this->activeTab.'_'.date('Y-m-d_His')),

                        ExcelExport::make('resumido')
                            ->label('Reporte Resumido')
                            ->fromTable()
                            ->withFilename(fn () => 'Ventas_Resumen_'.$this->activeTab.'_'.date('Y-m-d')),
                    ]),
            ]);
    }

    protected function calculateTotalCosts(): float
    {
        $startDate = $this->parseDate($this->filters['start_date']);
        $endDate = $this->parseDate($this->filters['end_date']);
        $currency = $this->activeTab;

        return Sale::query()
            ->where('currency', $currency)
            ->when($startDate, fn ($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->when(! empty($this->filters['source']) && $this->filters['source'] !== 'all', fn ($q) => $q->where('source', $this->filters['source']))
            ->when(! empty($this->filters['product_type']) && $this->filters['product_type'] !== 'all', fn ($q) => $q->whereHas('items.product', fn ($sq) => $sq->where('type', $this->filters['product_type'])))
            ->when(! empty($this->filters['payment_method_id']) && $this->filters['payment_method_id'] !== 'all', fn ($q) => $q->where('payment_method_id', $this->filters['payment_method_id']))
            ->when(! empty($this->filters['client_id']), fn ($q) => $q->where('client_id', $this->filters['client_id']))
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->with(['items', 'paymentMethod'])
            ->get()
            ->sum(function ($sale) use ($currency) {
                // Detectar si el método de pago es de Nicaragua USD
                $isNicaraguaUsd = false;
                if ($sale->paymentMethod) {
                    $isNicaraguaUsd = str_contains(strtolower($sale->paymentMethod->name), 'nicaragua');
                }

                return $sale->items->sum(function ($item) use ($currency, $sale, $isNicaraguaUsd) {
                    // Si es método de pago Nicaragua USD, usar base_price_usd_nic
                    if ($isNicaraguaUsd && ($item->base_price_usd_nic ?? 0) > 0) {
                        return $item->base_price_usd_nic * $item->quantity;
                    }
                    if (in_array($currency, ['USD', 'USDT'])) {
                        return ($item->base_price ?? 0) * $item->quantity;
                    }
                    if ($currency === 'NIO' && ($item->base_price_nio ?? 0) > 0) {
                        return $item->base_price_nio * $item->quantity;
                    }
                    $basePriceUsd = $item->base_price ?? 0;
                    $exchangeRate = $sale->exchange_rate_used ?? 1;
                    return ($basePriceUsd * $exchangeRate) * $item->quantity;
                });
            });
    }

    protected function calculateTotalProfit(): float
    {
        $startDate = $this->parseDate($this->filters['start_date']);
        $endDate = $this->parseDate($this->filters['end_date']);
        $currency = $this->activeTab;

        $sales = Sale::query()
            ->where('currency', $currency)
            ->when($startDate, fn ($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->when(! empty($this->filters['source']) && $this->filters['source'] !== 'all', fn ($q) => $q->where('source', $this->filters['source']))
            ->when(! empty($this->filters['product_type']) && $this->filters['product_type'] !== 'all', fn ($q) => $q->whereHas('items.product', fn ($sq) => $sq->where('type', $this->filters['product_type'])))
            ->when(! empty($this->filters['payment_method_id']) && $this->filters['payment_method_id'] !== 'all', fn ($q) => $q->where('payment_method_id', $this->filters['payment_method_id']))
            ->when(! empty($this->filters['client_id']), fn ($q) => $q->where('client_id', $this->filters['client_id']))
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->with(['items', 'paymentMethod'])
            ->get();

        return $sales->sum(function ($sale) use ($currency) {
            // Detectar si el método de pago es de Nicaragua USD
            $isNicaraguaUsd = false;
            if ($sale->paymentMethod) {
                $isNicaraguaUsd = str_contains(strtolower($sale->paymentMethod->name), 'nicaragua');
            }

            $total = $sale->total_amount;
            $cost = $sale->items->sum(function ($item) use ($currency, $sale, $isNicaraguaUsd) {
                // Si es método de pago Nicaragua USD, usar base_price_usd_nic
                if ($isNicaraguaUsd && ($item->base_price_usd_nic ?? 0) > 0) {
                    return $item->base_price_usd_nic * $item->quantity;
                }
                if (in_array($currency, ['USD', 'USDT'])) {
                    return ($item->base_price ?? 0) * $item->quantity;
                }
                if ($currency === 'NIO' && ($item->base_price_nio ?? 0) > 0) {
                    return $item->base_price_nio * $item->quantity;
                }
                $basePriceUsd = $item->base_price ?? 0;
                $exchangeRate = $sale->exchange_rate_used ?? 1;
                return ($basePriceUsd * $exchangeRate) * $item->quantity;
            });
            return $total - $cost;
        });
    }

    public function getStatsForCurrency(string $currency): array
    {
        $startDate = $this->parseDate($this->filters['start_date']) ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->parseDate($this->filters['end_date']) ?? now()->format('Y-m-d');

        $query = Sale::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate);

        // Solo filtrar por moneda si no es "ALL"
        if ($currency !== 'ALL') {
            $query->where('currency', $currency);
        }

        if (! empty($this->filters['source']) && $this->filters['source'] !== 'all') {
            $query->where('source', $this->filters['source']);
        }
        if (! empty($this->filters['payment_method_id']) && $this->filters['payment_method_id'] !== 'all') {
            $query->where('payment_method_id', $this->filters['payment_method_id']);
        }
        if (! empty($this->filters['client_id'])) {
            $query->where('client_id', $this->filters['client_id']);
        }
        if (! empty($this->filters['product_type']) && $this->filters['product_type'] !== 'all') {
            $query->whereHas('items.product', function ($q) {
                $q->where('type', $this->filters['product_type']);
            });
        }

        // Para "ALL", sumar amount_usd para tener un total comparable en USD
        if ($currency === 'ALL') {
            $totalIngresos = $query->sum('amount_usd');
        } else {
            $totalIngresos = $query->sum('total_amount');
        }

        $totalVentas = $query->count();

        return [
            'total_ingresos' => $totalIngresos,
            'total_ventas' => $totalVentas,
            'promedio_venta' => $totalVentas > 0 ? $totalIngresos / $totalVentas : 0,
        ];
    }

    public function getActiveCurrencies(): array
    {
        $currencies = Currency::where('is_active', true)
            ->pluck('name', 'code')
            ->toArray();

        // Empezar con "TODAS" como primera opción
        $ordered = ['ALL' => 'Todas las Monedas'];

        if (isset($currencies['USD'])) {
            $ordered['USD'] = $currencies['USD'];
            unset($currencies['USD']);
        }

        if (isset($currencies['NIO'])) {
            $ordered['NIO'] = $currencies['NIO'];
            unset($currencies['NIO']);
        }

        ksort($currencies);

        return array_merge($ordered, $currencies);
    }

    public function getCurrencySymbol(string $code): string
    {
        return match ($code) {
            'ALL' => '🌐',
            'USD' => '$',
            'NIO' => 'C$',
            'EUR' => '€',
            'MXN' => 'MX$',
            default => $code.' ',
        };
    }
}
