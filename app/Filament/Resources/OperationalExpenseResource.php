<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalExpenseResource\Pages;
use App\Models\Expense;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperationalExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationLabel = 'Gastos Operativos';
    protected static ?string $modelLabel = 'Gasto Operativo';
    protected static ?string $pluralModelLabel = 'Gastos Operativos';
    protected static ?string $navigationGroup = 'Gestión';
    protected static ?int $navigationSort = 5;

    // Filtrar solo gastos operativos
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('expense_type', 'operational');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registrar Gasto Operativo')
                    ->description('Registre gastos de la empresa como hosting, programador, servicios externos, etc.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('payment_date')
                                    ->label('Fecha del Gasto')
                                    ->default(now())
                                    ->required()
                                    ->native(false)
                                    ->closeOnDateSelection(),

                                Forms\Components\TextInput::make('expense_name')
                                    ->label('Nombre del Gasto')
                                    ->placeholder('Ej: Pago Programador, Hosting, Servicio Externo')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Nombre descriptivo del gasto'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción del Gasto')
                            ->placeholder('Detalle qué incluye este gasto, período, etc.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('payment_method_id')
                            ->label('Método de Pago')
                            ->relationship(
                                name: 'paymentMethod',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name')
                            )
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $paymentMethod = \App\Models\PaymentMethod::find($state);
                                    if ($paymentMethod) {
                                        $set('currency', $paymentMethod->currency);
                                    }
                                }
                            })
                            ->helperText('Seleccione cómo pagó este gasto')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('payment_reference')
                                    ->label('Referencia de Pago')
                                    ->placeholder('Ej: Transfer-12345, Factura #678')
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
                                    ->required()
                                    ->native(false)
                                    ->disabled() // Se define automáticamente por el método de pago
                                    ->dehydrated()
                                    ->helperText('✓ Auto-detectado del método de pago'),
                            ]),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto Pagado')
                            ->numeric()
                            ->prefix(function (Get $get) {
                                $currency = $get('currency');
                                return match($currency) {
                                    'NIO' => 'C$',
                                    'USD' => '$',
                                    'USDT' => '$',
                                    default => '$',
                                };
                            })
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('0.00')
                            ->helperText('💸 Cantidad que pagó por este gasto')
                            ->columnSpanFull(),

                        // Campos ocultos
                        Forms\Components\Hidden::make('expense_type')
                            ->default('operational')
                            ->dehydrated(),

                        Forms\Components\Hidden::make('amount_usd')
                            ->dehydrated()
                            ->default(0),

                        Forms\Components\Hidden::make('credits_received')
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

                Tables\Columns\TextColumn::make('expense_name')
                    ->label('Gasto')
                    ->weight('bold')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(function ($record) {
                        $symbol = match($record->currency) {
                            'NIO' => 'C$',
                            'USD' => '$',
                            'USDT' => '$',
                            default => $record->currency . ' ',
                        };
                        return $symbol . number_format($record->amount, 2) . ' ' . $record->currency;
                    })
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Método')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(40)
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Referencia')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options([
                        'USD' => 'USD - Dólares',
                        'NIO' => 'NIO - Córdobas',
                        'USDT' => 'USDT - Tether',
                        'MXN' => 'MXN - Pesos Mexicanos',
                        'COP' => 'COP - Pesos Colombianos',
                    ])
                    ->placeholder('Todas las monedas'),

                Tables\Filters\SelectFilter::make('payment_method_id')
                    ->label('Método de Pago')
                    ->relationship('paymentMethod', 'name')
                    ->placeholder('Todos los métodos')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver Detalle')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalle del Gasto Operativo')
                    ->modalSubmitAction(false) // No mostrar botón de guardar
                    ->modalCancelActionLabel('Cerrar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Gasto Operativo')
                    ->modalDescription('⚠️ ADVERTENCIA: Al eliminar este gasto operativo, se revertirá la sumatoria de los reportes e indicadores financieros. Esta acción NO se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar gasto')
                    ->successNotificationTitle('Gasto eliminado')
                    ->successNotification(function () {
                        return \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Gasto Operativo Eliminado')
                            ->body('El gasto ha sido eliminado y los reportes se han actualizado automáticamente.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Gastos Operativos Seleccionados')
                        ->modalDescription('⚠️ ADVERTENCIA: Al eliminar estos gastos operativos, se revertirán las sumatorias de los reportes e indicadores financieros. Esta acción NO se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar todos')
                        ->successNotificationTitle('Gastos eliminados')
                        ->successNotification(function ($records) {
                            $count = count($records);
                            return \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Gastos Operativos Eliminados')
                                ->body("{$count} gasto(s) eliminado(s). Los reportes se han actualizado automáticamente.")
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperationalExpenses::route('/'),
            'create' => Pages\CreateOperationalExpense::route('/create'),
            // Página de edición eliminada - Los gastos operativos NO se pueden editar
            // Solo se pueden ver (ViewAction) y eliminar (DeleteAction)
        ];
    }
}
