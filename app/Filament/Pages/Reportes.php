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
        'currency' => 'USD',
    ];

    public function mount(): void
    {
        // Valores iniciales (Mes actual)
        $this->form->fill([
            'startDate' => now()->startOfMonth(),
            'endDate' => now()->endOfDay(),
            'currency' => 'USD',
        ]);
    }

    // --- 1. FORMULARIO REACTIVO (LIVE) ---
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtros de Reporte')
                    ->description('Los datos se actualizan automáticamente al cambiar las fechas o moneda.')
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

                                Forms\Components\Select::make('currency')
                                    ->label('Moneda')
                                    ->options([
                                        'USD' => 'Dólares (USD)',
                                        'NIO' => 'Córdobas (NIO)',
                                    ])
                                    ->default('USD')
                                    ->required()
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
            // Aplicar filtros
            ->modifyQueryUsing(function (Builder $query) {
                $data = $this->filters;
                
                if ($data['startDate'] ?? null) {
                    $query->whereDate('sale_date', '>=', $data['startDate']);
                }
                if ($data['endDate'] ?? null) {
                    $query->whereDate('sale_date', '<=', $data['endDate']);
                }
                if ($data['currency'] ?? null) {
                    $query->where('currency', $data['currency']);
                }
                
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
                    ->icon('heroicon-m-credit-card'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Monto')
                    ->money(fn ($record) => $record->currency)
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('TOTAL')->money()),
            ])
            ->defaultSort('sale_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Descargar')
                    ->color('success')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn () => 'Reporte_NicaGSM_' . date('Y-m-d'))
                            ->askForWriterType()
                            ->withColumns([
                                Column::make('id')->heading('ID'),
                                Column::make('sale_date')->heading('Fecha'),
                                Column::make('client.name')->heading('Cliente'),
                                Column::make('source')->heading('Origen'),
                                Column::make('status')->heading('Estado'),
                                Column::make('paymentMethod.name')->heading('Método'),
                                Column::make('total_amount')->heading('Monto'),
                                Column::make('currency')->heading('Moneda'),
                            ]),
                    ]),
            ]);
    }
    
    public function filterTable()
    {
        // Se ejecuta para refrescar componentes
    }
}