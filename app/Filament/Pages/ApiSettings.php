<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;

class ApiSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Configuración API';
    protected static ?string $title = 'Configuración de APIs';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.api-settings';

    public ?array $data = [];

    /**
     * Obtener credenciales WooCommerce (BD > .env)
     */
    public static function getWooUrl(): ?string
    {
        return Setting::get('woo_url') ?: env('WOO_URL');
    }

    public static function getWooKey(): ?string
    {
        return Setting::get('woo_key') ?: env('WOO_KEY');
    }

    public static function getWooSecret(): ?string
    {
        return Setting::get('woo_secret') ?: env('WOO_SECRET');
    }

    /**
     * Obtener credenciales DHRU (BD > .env)
     */
    public static function getDhruUrl(): ?string
    {
        return Setting::get('dhru_url') ?: env('DHRU_URL');
    }

    public static function getDhruKey(): ?string
    {
        return Setting::get('dhru_key') ?: env('DHRU_KEY');
    }

    public static function getDhruUsername(): ?string
    {
        return Setting::get('dhru_username') ?: env('DHRU_USERNAME');
    }

    public function mount(): void
    {
        $this->form->fill([
            // WooCommerce - Prioridad: BD > .env
            'woocommerce_url' => static::getWooUrl(),
            'woocommerce_consumer_key' => static::getWooKey(),
            'woocommerce_consumer_secret' => static::getWooSecret(),

            // DHRU - Prioridad: BD > .env
            'dhru_api_url' => static::getDhruUrl(),
            'dhru_api_key' => static::getDhruKey(),
            'dhru_username' => static::getDhruUsername(),
        ]);
    }

    /**
     * Verificar si WooCommerce está conectado
     */
    public function isWooCommerceConnected(): bool
    {
        return !empty(static::getWooUrl())
            && !empty(static::getWooKey())
            && !empty(static::getWooSecret());
    }

    /**
     * Verificar si DHRU está conectado
     */
    public function isDhruConnected(): bool
    {
        return !empty(static::getDhruUrl())
            && !empty(static::getDhruKey());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // WOOCOMMERCE
                Forms\Components\Section::make('WooCommerce API')
                    ->description('Configuración de conexión con WooCommerce para sincronización de productos y pedidos.')
                    ->icon('heroicon-o-shopping-bag')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('disconnect_woo')
                            ->label('Desconectar API')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('¿Desconectar WooCommerce API?')
                            ->modalDescription('Se eliminarán todas las credenciales de WooCommerce. Esta acción requiere confirmación.')
                            ->modalSubmitActionLabel('Sí, Desconectar')
                            ->action(function () {
                                Setting::set('woo_url', null, 'api');
                                Setting::set('woo_key', null, 'api');
                                Setting::set('woo_secret', null, 'api');

                                $this->form->fill([
                                    'woocommerce_url' => null,
                                    'woocommerce_consumer_key' => null,
                                    'woocommerce_consumer_secret' => null,
                                ]);

                                Notification::make()
                                    ->warning()
                                    ->title('WooCommerce Desconectado')
                                    ->body('Las credenciales han sido eliminadas')
                                    ->send();

                                $this->redirect(request()->header('Referer'));
                            })
                            ->visible(fn () => $this->isWooCommerceConnected()),
                    ])
                    ->schema([
                        // Badge de estado
                        Forms\Components\ViewField::make('woo_badge')
                            ->label('')
                            ->view('filament.components.api-status-badge', [
                                'connected' => $this->isWooCommerceConnected(),
                                'label' => $this->isWooCommerceConnected() ? '✓ Conectada' : 'No Conectada',
                            ])
                            ->columnSpanFull(),

                        // Mostrar info si está conectada
                        Forms\Components\ViewField::make('woo_info')
                            ->label('')
                            ->view('filament.components.api-info', [
                                'connected' => $this->isWooCommerceConnected(),
                                'items' => $this->isWooCommerceConnected() ? [
                                    ['icon' => '🌐', 'label' => 'URL', 'value' => static::getWooUrl()],
                                    ['icon' => '🔑', 'label' => 'Consumer Key', 'value' => substr(static::getWooKey() ?? '', 0, 15) . '...'],
                                    ['icon' => '🔒', 'label' => 'Consumer Secret', 'value' => 'Configurado'],
                                ] : [],
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('woocommerce_url')
                            ->label('URL de la Tienda')
                            ->placeholder('https://tusitio.com')
                            ->url()
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->helperText('URL completa de tu tienda WooCommerce')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('woocommerce_consumer_key')
                            ->label('Consumer Key')
                            ->placeholder('ck_xxxxxxxxxxxxxxxxxxxxx')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-key')
                            ->helperText('🔐 Clave pública de WooCommerce')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('woocommerce_consumer_secret')
                            ->label('Consumer Secret')
                            ->placeholder('cs_xxxxxxxxxxxxxxxxxxxxx')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-lock-closed')
                            ->helperText('🔐 Clave secreta de WooCommerce')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn () => $this->isWooCommerceConnected())
                    ->columns(2),

                // DHRU
                Forms\Components\Section::make('DHRU Fusion API')
                    ->description('Configuración de conexión con DHRU Fusion para gestión de servicios de servidor.')
                    ->icon('heroicon-o-server')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('disconnect_dhru')
                            ->label('Desconectar API')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('¿Desconectar DHRU Fusion API?')
                            ->modalDescription('Se eliminarán todas las credenciales de DHRU. Esta acción requiere confirmación.')
                            ->modalSubmitActionLabel('Sí, Desconectar')
                            ->action(function () {
                                Setting::set('dhru_url', null, 'api');
                                Setting::set('dhru_username', null, 'api');
                                Setting::set('dhru_key', null, 'api');

                                $this->form->fill([
                                    'dhru_api_url' => null,
                                    'dhru_username' => null,
                                    'dhru_api_key' => null,
                                ]);

                                Notification::make()
                                    ->warning()
                                    ->title('DHRU Fusion Desconectado')
                                    ->body('Las credenciales han sido eliminadas')
                                    ->send();

                                $this->redirect(request()->header('Referer'));
                            })
                            ->visible(fn () => $this->isDhruConnected()),
                    ])
                    ->schema([
                        // Badge de estado
                        Forms\Components\ViewField::make('dhru_badge')
                            ->label('')
                            ->view('filament.components.api-status-badge', [
                                'connected' => $this->isDhruConnected(),
                                'label' => $this->isDhruConnected() ? '✓ Conectada' : 'No Conectada',
                            ])
                            ->columnSpanFull(),

                        // Mostrar info si está conectada
                        Forms\Components\ViewField::make('dhru_info')
                            ->label('')
                            ->view('filament.components.api-info', [
                                'connected' => $this->isDhruConnected(),
                                'items' => $this->isDhruConnected() ? [
                                    ['icon' => '🌐', 'label' => 'URL', 'value' => static::getDhruUrl()],
                                    ['icon' => '👤', 'label' => 'Usuario', 'value' => static::getDhruUsername() ?: 'No configurado'],
                                    ['icon' => '🔑', 'label' => 'API Key', 'value' => substr(static::getDhruKey() ?? '', 0, 15) . '...'],
                                ] : [],
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('dhru_api_url')
                            ->label('URL de API')
                            ->placeholder('https://api.dhru.com')
                            ->url()
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->helperText('URL del servidor DHRU Fusion API')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('dhru_username')
                            ->label('Usuario / Username')
                            ->placeholder('tu_usuario')
                            ->prefixIcon('heroicon-o-user')
                            ->helperText('Nombre de usuario de DHRU Fusion')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('dhru_api_key')
                            ->label('API Key')
                            ->placeholder('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-key')
                            ->helperText('🔐 Clave de API de DHRU Fusion')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn () => $this->isDhruConnected())
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            // Guardar WooCommerce en BD (encriptado)
            Setting::set('woo_url', $data['woocommerce_url'] ?? null, 'api', false);
            Setting::set('woo_key', $data['woocommerce_consumer_key'] ?? null, 'api', true);
            Setting::set('woo_secret', $data['woocommerce_consumer_secret'] ?? null, 'api', true);

            // Guardar DHRU en BD (encriptado)
            Setting::set('dhru_url', $data['dhru_api_url'] ?? null, 'api', false);
            Setting::set('dhru_username', $data['dhru_username'] ?? null, 'api', false);
            Setting::set('dhru_key', $data['dhru_api_key'] ?? null, 'api', true);

            // Limpiar cache de config
            Artisan::call('config:clear');

            Notification::make()
                ->success()
                ->title('Configuración Guardada')
                ->body('Las credenciales han sido guardadas de forma segura en la base de datos.')
                ->send();

            // Refrescar la página
            $this->redirect(request()->header('Referer'));

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error al Guardar')
                ->body('No se pudieron guardar las credenciales: ' . $e->getMessage())
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Guardar Configuración')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->submit('save'),
        ];
    }
}
