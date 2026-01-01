<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
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
        $details = ['Precio Base' => '$' . number_format($record->base_price ?? 0, 2)];
        if ($record->sku) {
            $details['SKU'] = $record->sku;
        }
        return $details;
    }

    public static function form(Form $form): Form
    {
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

                Forms\Components\Section::make('💰 Precios Base por Proveedor')
                    ->description('Configure el precio base (costo) para cada proveedor. Al vender, se usará el precio del proveedor seleccionado.')
                    ->schema([
                        Forms\Components\Repeater::make('supplierPrices')
                            ->relationship()
                            ->label('Precios por Proveedor')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Proveedor')
                                            ->relationship('supplier', 'name')
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->searchable(),

                                        Forms\Components\TextInput::make('base_price')
                                            ->label('Precio Base (USD)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->default(0)
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->required()
                                            ->helperText('Costo de este producto con este proveedor'),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('+ Agregar Proveedor')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                \App\Models\Supplier::find($state['supplier_id'])?->name . ' - $' . number_format($state['base_price'] ?? 0, 2)
                            )
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('supplier_prices_note')
                            ->label('📝 Nota Importante')
                            ->content('Al reportar una venta, deberá seleccionar el proveedor y el sistema usará automáticamente el precio base de ese proveedor. Los paquetes de cliente (abajo) se aplicarán sobre el precio del proveedor elegido.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Section::make('📦 Precios para Paquetes de Cliente')
                    ->description('Configure los precios de venta para cada paquete de cliente. Estos precios se mostrarán automáticamente según el paquete del cliente.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price_package_1')
                                    ->label('📦 Paquete 1 (Premium)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Precio para clientes Premium'),

                                Forms\Components\TextInput::make('price_package_2')
                                    ->label('📦 Paquete 2 (Mayorista)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Precio para Mayoristas'),

                                Forms\Components\TextInput::make('price_package_3')
                                    ->label('📦 Paquete 3 (Minorista)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Precio para Minoristas'),

                                Forms\Components\TextInput::make('price_package_4')
                                    ->label('📦 Paquete 4 (Especial)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Precio Especial'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(true),
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
                    ->limit(50),

                Tables\Columns\TextColumn::make('base_price')
                    ->label('Precio Base')
                    ->money('USD')
                    ->sortable()
                    ->default('0.00'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'service' => 'warning',
                        'server_credit' => 'info',
                        'digital_product' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'service' => 'Servicio',
                        'server_credit' => 'Crédito',
                        'digital_product' => 'Tienda',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            // Mostrar solo productos NO eliminados (deleted_at IS NULL)
            // La sincronización WooCommerce restaura productos con deleted_at = null
            ->modifyQueryUsing(fn ($query) => $query
                ->whereNull('deleted_at')
                ->orderByRaw("
                    CASE type
                        WHEN 'digital_product' THEN 1
                        WHEN 'service' THEN 2
                        WHEN 'server_credit' THEN 3
                        ELSE 4
                    END, name ASC
                ")
            )
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options([
                        'digital_product' => '🏪 Artículo Tienda',
                        'service' => '🖥️ Servicio Servidor',
                        'server_credit' => '💳 Crédito Servidor',
                    ])
                    ->native(false)
                    ->placeholder('Todos los tipos'),
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