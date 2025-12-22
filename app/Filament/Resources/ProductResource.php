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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Producto / Servicio')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Artículo / Servicio')
                            ->required()
                            ->columnSpanFull(),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Precio Venta')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                    
                                Forms\Components\Select::make('type')
                                    ->label('Tipo')
                                    ->options([
                                        'digital_product' => 'Artículo Tienda', // Mapeado para WooCommerce
                                        'service' => 'Servicio Servidor',
                                        'server_credit' => 'Crédito Servidor',
                                    ])
                                    ->default('digital_product')
                                    ->required(),

                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU (Código)')
                                    ->placeholder('Sincronizable con Tienda')
                                    ->maxLength(255),
                            ]),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para venta (Stock / Activo)')
                            ->default(true)
                            ->inline(false),
                    ])->columns(1),
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
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'service' => 'Servicio Servidor',
                        'server_credit' => 'Crédito Servidor',
                        'digital_product' => 'Artículo Tienda',
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
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'digital_product' => 'Tienda',
                        'service' => 'Servicio',
                        'server_credit' => 'Crédito',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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