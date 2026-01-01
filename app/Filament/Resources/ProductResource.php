<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Biblioteca de Servicios';
    protected static ?string $modelLabel = 'Producto/Servicio';
    protected static ?string $navigationGroup = 'Gestión';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $details = ['Precio' => '$' . number_format($record->price, 2)];
        if ($record->sku) {
            $details['SKU'] = $record->sku;
        }
        return $details;
    }

    public static function form(Form $form): Form
    {
        // Obtener proveedores del sistema
        $suppliers = Supplier::all();

        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Producto / Servicio')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Artículo / Servicio')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->placeholder('Ejemplo: VPS Cloud 2GB RAM')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tipo')
                                    ->options([
                                        'digital_product' => 'Artículo Tienda',
                                        'service' => 'Servicio Servidor',
                                        'server_credit' => 'Crédito Servidor',
                                    ])
                                    ->default('digital_product')
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU (Código)')
                                    ->placeholder('Sincronizable con Tienda')
                                    ->maxLength(255)
                                    ->alphaDash(),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para venta (Stock / Activo)')
                            ->default(true)
                            ->inline(false),
                    ])->columns(1),

                // SECCIÓN: Precios Base por Proveedor
                Forms\Components\Section::make('Precios Base por Proveedor')
                    ->description('Define el precio de costo para cada proveedor configurado')
                    ->schema(
                        $suppliers->count() > 0
                            ? $suppliers->map(function ($supplier) {
                                return Forms\Components\TextInput::make("base_prices.{$supplier->id}")
                                    ->label($supplier->name)
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->helperText($supplier->website ? "({$supplier->website})" : null);
                            })->toArray()
                            : [
                                Forms\Components\Placeholder::make('no_suppliers')
                                    ->label('')
                                    ->content('No hay proveedores configurados. Agrega proveedores en el módulo de Proveedores.')
                            ]
                    )
                    ->columns(2)
                    ->collapsible(),

                // SECCIÓN: Precios de Venta por Paquete
                Forms\Components\Section::make('Precios de Venta por Paquete')
                    ->description('Define los 4 precios de venta según el paquete del cliente')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('price_pack_1')
                                    ->label('Paquete 1 (Básico)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->placeholder('0.00'),

                                Forms\Components\TextInput::make('price_pack_2')
                                    ->label('Paquete 2 (Estándar)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->placeholder('0.00'),

                                Forms\Components\TextInput::make('price_pack_3')
                                    ->label('Paquete 3 (Premium)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->placeholder('0.00'),

                                Forms\Components\TextInput::make('price_pack_4')
                                    ->label('Paquete 4 (Mayorista)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01)
                                    ->placeholder('0.00'),
                            ]),

                        // Precio principal (legacy/referencia)
                        Forms\Components\TextInput::make('price')
                            ->label('Precio General (Referencia)')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->placeholder('0.00')
                            ->helperText('Precio por defecto si no aplica ningún paquete'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(), // Permite que nombres largos de variantes bajen de línea
                    
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'service' => 'warning',
                        'server_credit' => 'info',
                        'digital_product' => 'success',
                        'store' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'service' => 'Servicio Servidor',
                        'server_credit' => 'Crédito Servidor',
                        'digital_product' => 'Artículo Tienda',
                        'store' => 'Tienda',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->color('gray'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Disp.')
                    ->boolean(),
            ])
            ->defaultSort('type', 'asc') // Ordenar por tipo: store primero, luego service, luego server_credit
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Producto')
                    ->options([
                        'store' => '🏪 Tienda (WooCommerce)',
                        'digital_product' => '📦 Artículo Digital',
                        'service' => '🖥️ Servicio Servidor',
                        'server_credit' => '💳 Crédito Servidor',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Producto/Servicio')
                    ->modalDescription('¿Está seguro que desea eliminar este producto/servicio?')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Productos/Servicios')
                        ->modalDescription('¿Está seguro que desea eliminar los productos/servicios seleccionados?')
                        ->modalSubmitActionLabel('Sí, eliminar todos'),
                ]),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}