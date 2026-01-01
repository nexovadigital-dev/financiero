<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;

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

    public function mount(): void
    {
        $this->form->fill([
            // WooCommerce
            'woocommerce_url' => env('WOO_URL'),
            'woocommerce_consumer_key' => env('WOO_KEY'),
            'woocommerce_consumer_secret' => env('WOO_SECRET'),

            // DHRU
            'dhru_api_url' => env('DHRU_URL'),
            'dhru_api_key' => env('DHRU_KEY'),
            'dhru_username' => env('DHRU_USERNAME'),
        ]);
    }

    /**
     * Verificar si WooCommerce está conectado
     */
    public function isWooCommerceConnected(): bool
    {
        return !empty(env('WOO_URL'))
            && !empty(env('WOO_KEY'))
            && !empty(env('WOO_SECRET'));
    }

    /**
     * Verificar si DHRU está conectado
     */
    public function isDhruConnected(): bool
    {
        return !empty(env('DHRU_URL'))
            && !empty(env('DHRU_KEY'));
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
                            ->modalDescription('Se eliminarán todas las credenciales de WooCommerce del archivo .env. Esta acción requiere confirmación.')
                            ->modalSubmitActionLabel('Sí, Desconectar')
                            ->action(function () {
                                $this->updateEnvVariable('WOO_URL', '');
                                $this->updateEnvVariable('WOO_KEY', '');
                                $this->updateEnvVariable('WOO_SECRET', '');

                                $this->form->fill([
                                    'woocommerce_url' => null,
                                    'woocommerce_consumer_key' => null,
                                    'woocommerce_consumer_secret' => null,
                                ]);

                                Notification::make()
                                    ->warning()
                                    ->title('WooCommerce Desconectado')
                                    ->body('Las credenciales han sido eliminadas del archivo .env')
                                    ->send();

                                redirect()->to(request()->header('Referer'));
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
                                    ['icon' => '🌐', 'label' => 'URL', 'value' => env('WOO_URL')],
                                    ['icon' => '🔑', 'label' => 'Consumer Key', 'value' => substr(env('WOO_KEY'), 0, 15) . '...'],
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
                            ->modalDescription('Se eliminarán todas las credenciales de DHRU del archivo .env. Esta acción requiere confirmación.')
                            ->modalSubmitActionLabel('Sí, Desconectar')
                            ->action(function () {
                                $this->updateEnvVariable('DHRU_URL', '');
                                $this->updateEnvVariable('DHRU_USERNAME', '');
                                $this->updateEnvVariable('DHRU_KEY', '');

                                $this->form->fill([
                                    'dhru_api_url' => null,
                                    'dhru_username' => null,
                                    'dhru_api_key' => null,
                                ]);

                                Notification::make()
                                    ->warning()
                                    ->title('DHRU Fusion Desconectado')
                                    ->body('Las credenciales han sido eliminadas del archivo .env')
                                    ->send();

                                redirect()->to(request()->header('Referer'));
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
                                    ['icon' => '🌐', 'label' => 'URL', 'value' => env('DHRU_URL')],
                                    ['icon' => '👤', 'label' => 'Usuario', 'value' => env('DHRU_USERNAME') ?: 'No configurado'],
                                    ['icon' => '🔑', 'label' => 'API Key', 'value' => substr(env('DHRU_KEY'), 0, 15) . '...'],
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
            // Guardar WooCommerce en .env
            $this->updateEnvVariable('WOO_URL', $data['woocommerce_url'] ?? '');
            $this->updateEnvVariable('WOO_KEY', $data['woocommerce_consumer_key'] ?? '');
            $this->updateEnvVariable('WOO_SECRET', $data['woocommerce_consumer_secret'] ?? '');

            // Guardar DHRU en .env
            $this->updateEnvVariable('DHRU_URL', $data['dhru_api_url'] ?? '');
            $this->updateEnvVariable('DHRU_USERNAME', $data['dhru_username'] ?? '');
            $this->updateEnvVariable('DHRU_KEY', $data['dhru_api_key'] ?? '');

            Notification::make()
                ->success()
                ->title('Configuración Guardada')
                ->body('Las credenciales han sido guardadas en el archivo .env de forma segura.')
                ->send();

            // Refrescar la página
            redirect()->to(request()->header('Referer'));

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error al Guardar')
                ->body('No se pudieron guardar las credenciales: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Actualizar variable en archivo .env
     */
    private function updateEnvVariable(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        // Escapar caracteres especiales en el valor
        $value = str_replace('"', '\"', $value);

        // Buscar si la variable ya existe
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            // Actualizar variable existente
            $envContent = preg_replace($pattern, "{$key}=\"{$value}\"", $envContent);
        } else {
            // Agregar nueva variable al final
            $envContent .= "\n{$key}=\"{$value}\"";
        }

        file_put_contents($envPath, $envContent);
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
