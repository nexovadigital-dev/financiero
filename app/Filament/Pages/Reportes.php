<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Filament\Widgets\IngresosChart;
use App\Filament\Widgets\MetodosPagoChart;
use App\Filament\Widgets\FinancialStats; // Importamos el nuevo widget financiero

class Reportes extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Reportes Financieros';
    protected static ?string $title = 'Panel de Reportes Avanzados';
    protected static ?string $navigationGroup = 'Gestión';
    
    // Vista personalizada
    protected static string $view = 'filament.pages.reportes';

    // Estado de los filtros
    public ?array $filters = [
        'startDate' => null,
        'endDate' => null,
        'source' => 'all',
        'payment_method_id' => null,
        'client_id' => null,
    ];

    public function mount(): void
    {
        // Valores iniciales (Mes actual)
        $this->form->fill([
            'startDate' => now()->startOfMonth(),
            'endDate' => now()->endOfDay(),
            'source' => 'all',
            'payment_method_id' => null,
            'client_id' => null,
        ]);
    }

    // --- 1. FORMULARIO REACTIVO (LIVE) ---
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtros Avanzados de Reporte')
                    ->description('Los datos se actualizan automáticamente. Todos los montos se muestran en USD para comparación universal.')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('startDate')
                                    ->label('Fecha Inicio')
                                    ->required()
                                    ->default(now()->startOfMonth())
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->filterTable()),

                                Forms\Components\DatePicker::make('endDate')
                                    ->label('Fecha Fin')
                                    ->required()
                                    ->default(now())
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->filterTable()),

                                Forms\Components\Select::make('source')
                                    ->label('Origen de Venta')
                                    ->options([
                                        'all' => '📊 Todos',
                                        'store' => '🏪 Solo Tienda',
                                        'server' => '🖥️ Solo Servidor',
                                    ])
                                    ->default('all')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->filterTable()),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('payment_method_id')
                                    ->label('Método de Pago')
                                    ->placeholder('Todos los métodos')
                                    ->options(\App\Models\PaymentMethod::pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->filterTable()),

                                Forms\Components\Select::make('client_id')
                                    ->label('Cliente Específico')
                                    ->placeholder('Todos los clientes')
                                    ->options(\App\Models\Client::pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->filterTable()),
                            ]),
                    ])
                    ->collapsible(),
            ])
            ->statePath('filters');
    }

    // --- 2. WIDGETS ---
    protected function getHeaderWidgets(): array
    {
        return [
            // El resumen financiero va PRIMERO para que salga arriba
            FinancialStats::class,
            
            // Luego los gráficos
            IngresosChart::class,
            MetodosPagoChart::class,
        ];
    }

    // --- 3. TABLA ---
    public function table(Table $table): Table
    {
        return $table
            ->query(Sale::query())
            // Aplicar filtros avanzados
            ->modifyQueryUsing(function (Builder $query) {
                $data = $this->filters;

                // Filtro por fecha
                if ($data['startDate'] ?? null) {
                    $query->whereDate('sale_date', '>=', $data['startDate']);
                }
                if ($data['endDate'] ?? null) {
                    $query->whereDate('sale_date', '<=', $data['endDate']);
                }

                // Filtro por origen (tienda/servidor)
                if (($data['source'] ?? 'all') !== 'all') {
                    $query->where('source', $data['source']);
                }

                // Filtro por método de pago
                if ($data['payment_method_id'] ?? null) {
                    $query->where('payment_method_id', $data['payment_method_id']);
                }

                // Filtro por cliente específico
                if ($data['client_id'] ?? null) {
                    $query->where('client_id', $data['client_id']);
                }

                // Solo ventas completadas
                $query->where('status', 'completed');
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sale_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable(),

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
                    ->color('info'),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Divisa')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Monto Original')
                    ->money(fn ($record) => $record->currency)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('amount_usd')
                    ->label('Monto USD')
                    ->money('USD')
                    ->weight('bold')
                    ->color('success')
                    ->getStateUsing(fn ($record) => $record->amount_usd ?? $record->total_amount)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('TOTAL USD')->money('USD')),

                Tables\Columns\IconColumn::make('manually_converted')
                    ->label('Manual')
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil')
                    ->falseIcon('heroicon-o-calculator')
                    ->tooltip(fn ($record) => $record->manually_converted ? 'Editado manualmente' : 'Calculado automáticamente'),
            ])
            ->defaultSort('sale_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Excel/CSV')
                    ->color('success')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn () => 'Reporte_NicaGSM_' . date('Y-m-d_His'))
                            ->askForWriterType()
                            ->withColumns([
                                Column::make('id')->heading('ID'),
                                Column::make('sale_date')->heading('Fecha'),
                                Column::make('client.name')->heading('Cliente'),
                                Column::make('source')->heading('Origen'),
                                Column::make('status')->heading('Estado'),
                                Column::make('paymentMethod.name')->heading('Método de Pago'),
                                Column::make('currency')->heading('Moneda'),
                                Column::make('total_amount')->heading('Monto Original'),
                                Column::make('amount_usd')->heading('Monto USD'),
                                Column::make('exchange_rate_used')->heading('Tasa Usada'),
                                Column::make('payment_reference')->heading('Referencia'),
                            ]),
                    ]),
            ]);
    }
    
    public function filterTable()
    {
        // Se ejecuta para refrescar componentes
    }
}