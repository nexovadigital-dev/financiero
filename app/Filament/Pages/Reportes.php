<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\IngresosChart;
use App\Filament\Widgets\MetodosPagoChart;
use App\Models\Currency;
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

    protected static ?string $title = 'Reportes Financieros Avanzados';

    protected static ?string $navigationGroup = 'Gestión';

    protected static string $view = 'filament.pages.reportes';

    public ?array $filters = [
        'start_date' => null,
        'end_date' => null,
        'currency' => 'USD',
        'source' => 'all',
        'payment_method_id' => 'all',
        'client_id' => null,
        'product_type' => 'all',
    ];

    public string $activeTab = 'USD';

    public function mount(): void
    {
        $this->activeTab = 'USD';
        $this->filters['currency'] = 'USD';
        $this->filters['start_date'] = now()->startOfMonth();
        $this->filters['end_date'] = now()->endOfDay();
    }

    public function updatedActiveTab($value): void
    {
        $this->filters['currency'] = $value;
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

    protected function getHeaderWidgets(): array
    {
        return [
            IngresosChart::class,
            MetodosPagoChart::class,
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
                $query->where('currency', $this->activeTab);

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

                Tables\Columns\TextColumn::make('items.product.name')
                    ->label('Productos')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->bulleted(),

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

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money(fn () => $this->activeTab)
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label('TOTAL INGRESOS')
                        ->money(fn () => $this->activeTab)),

                Tables\Columns\TextColumn::make('base_cost')
                    ->label('Costo')
                    ->money(fn () => $this->activeTab)
                    ->color('warning')
                    ->getStateUsing(fn ($record) => $record->items->sum(fn ($item) => ($item->base_price ?? 0) * $item->quantity))
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label('TOTAL COSTOS')
                        ->money(fn () => $this->activeTab)
                        ->using(fn ($query) => $this->calculateTotalCosts())),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Ganancia')
                    ->money(fn () => $this->activeTab)
                    ->weight('bold')
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        $total = $record->total_amount;
                        $cost = $record->items->sum(fn ($item) => ($item->base_price ?? 0) * $item->quantity);
                        return $total - $cost;
                    })
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label('GANANCIA NETA')
                        ->money(fn () => $this->activeTab)
                        ->using(fn ($query) => $this->calculateTotalProfit())),

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

        return Sale::query()
            ->where('currency', $this->activeTab)
            ->when($startDate, fn ($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->when(! empty($this->filters['source']) && $this->filters['source'] !== 'all', fn ($q) => $q->where('source', $this->filters['source']))
            ->when(! empty($this->filters['product_type']) && $this->filters['product_type'] !== 'all', fn ($q) => $q->whereHas('items.product', fn ($sq) => $sq->where('type', $this->filters['product_type'])))
            ->when(! empty($this->filters['payment_method_id']) && $this->filters['payment_method_id'] !== 'all', fn ($q) => $q->where('payment_method_id', $this->filters['payment_method_id']))
            ->when(! empty($this->filters['client_id']), fn ($q) => $q->where('client_id', $this->filters['client_id']))
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->with('items')
            ->get()
            ->sum(fn ($sale) => $sale->items->sum(fn ($item) => ($item->base_price ?? 0) * $item->quantity));
    }

    protected function calculateTotalProfit(): float
    {
        $startDate = $this->parseDate($this->filters['start_date']);
        $endDate = $this->parseDate($this->filters['end_date']);

        $sales = Sale::query()
            ->where('currency', $this->activeTab)
            ->when($startDate, fn ($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->when(! empty($this->filters['source']) && $this->filters['source'] !== 'all', fn ($q) => $q->where('source', $this->filters['source']))
            ->when(! empty($this->filters['product_type']) && $this->filters['product_type'] !== 'all', fn ($q) => $q->whereHas('items.product', fn ($sq) => $sq->where('type', $this->filters['product_type'])))
            ->when(! empty($this->filters['payment_method_id']) && $this->filters['payment_method_id'] !== 'all', fn ($q) => $q->where('payment_method_id', $this->filters['payment_method_id']))
            ->when(! empty($this->filters['client_id']), fn ($q) => $q->where('client_id', $this->filters['client_id']))
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->with('items')
            ->get();

        return $sales->sum(function ($sale) {
            $total = $sale->total_amount;
            $cost = $sale->items->sum(fn ($item) => ($item->base_price ?? 0) * $item->quantity);
            return $total - $cost;
        });
    }

    public function getStatsForCurrency(string $currency): array
    {
        $startDate = $this->parseDate($this->filters['start_date']) ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->parseDate($this->filters['end_date']) ?? now()->format('Y-m-d');

        $query = Sale::query()
            ->where('currency', $currency)
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate);

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

        $totalIngresos = $query->sum('total_amount');
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

        $ordered = [];

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
            'USD' => '$',
            'NIO' => 'C$',
            'EUR' => '€',
            'MXN' => 'MX$',
            default => $code.' ',
        };
    }
}
