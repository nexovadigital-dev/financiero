<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
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

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto Pagado')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                
                                Forms\Components\Select::make('currency')
                                    ->label('Moneda')
                                    ->options(['USD' => 'USD', 'NIO' => 'NIO'])
                                    ->default('USD')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('payment_method_id')
                            ->label('Método de Pago')
                            ->relationship('paymentMethod', 'name')
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Concepto / Detalle')
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
                Tables\Actions\DeleteAction::make(),
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