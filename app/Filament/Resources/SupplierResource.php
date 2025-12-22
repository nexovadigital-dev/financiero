<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Proveedores';
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $navigationGroup = 'Gestión';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Proveedor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Proveedor')
                            ->required()
                            ->minLength(2)
                            ->maxLength(255)
                            ->placeholder('Ingrese el nombre del proveedor')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('website')
                            ->label('Sitio Web')
                            ->url()
                            ->suffixIcon('heroicon-m-globe-alt')
                            ->placeholder('https://ejemplo.com')
                            ->maxLength(255)
                            ->validationMessages([
                                'url' => 'Debe ingresar una URL válida (ejemplo: https://google.com)',
                            ])
                            ->columnSpanFull(),
                    ])->columns(1), // Una sola columna para que se vea limpio
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('website')
                    ->label('Sitio Web')
                    ->icon('heroicon-m-link')
                    ->color('info')
                    ->url(fn ($state) => $state, true) // Hace clicable el link (abre en nueva pestaña)
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Proveedor')
                    ->modalDescription('¿Está seguro que desea eliminar este proveedor? También se eliminarán todos los pagos asociados.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Proveedores Seleccionados')
                        ->modalDescription('¿Está seguro que desea eliminar los proveedores seleccionados? También se eliminarán todos los pagos asociados.')
                        ->modalSubmitActionLabel('Sí, eliminar todos'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}