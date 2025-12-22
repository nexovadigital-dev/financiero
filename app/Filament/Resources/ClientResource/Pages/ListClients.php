<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Automattic\WooCommerce\Client as WooClient;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Cliente Local'),

            Actions\ActionGroup::make([
                // 1. IMPORTACIÓN MASIVA (Ahora con Bucle Infinito hasta terminar)
                Actions\Action::make('syncAll')
                    ->label('Importar TODOS (Masivo)')
                    ->icon('heroicon-o-users')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Importación Completa')
                    ->modalDescription('El sistema recorrerá página por página toda tu tienda WooCommerce para descargar absolutamente todos los clientes. Esto puede tomar unos minutos si son miles.')
                    ->action(fn () => $this->syncWooClients(null)),

                // 2. BUSCADOR ESPECÍFICO
                Actions\Action::make('searchWoo')
                    ->label('Buscar Cliente en Tienda')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->form([
                        TextInput::make('search_term')
                            ->label('Buscar por Nombre o Email')
                            ->placeholder('Ej: roberto@gmail.com o Roberto')
                            ->required(),
                    ])
                    ->modalHeading('Buscar e Importar')
                    ->modalDescription('Busca un cliente específico y tráelo al sistema.')
                    ->action(function (array $data) {
                        $this->syncWooClients($data['search_term']);
                    }),
            ])
            ->label('Conexión WooCommerce')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->button(),
        ];
    }

    public function syncWooClients($searchTerm = null)
    {
        if (!env('WOO_URL') || !env('WOO_KEY')) {
            Notification::make()->title('Error: Faltan credenciales en .env')->danger()->send();
            return;
        }

        try {
            $woocommerce = new WooClient(
                env('WOO_URL'),
                env('WOO_KEY'),
                env('WOO_SECRET'),
                ['version' => 'wc/v3', 'verify_ssl' => false, 'timeout' => 60] // Aumentamos timeout
            );

            $page = 1;
            $count = 0;
            $keepFetching = true;

            // Notificación de inicio
            Notification::make()->title('Iniciando sincronización...')->info()->send();

            // --- BUCLE DE PAGINACIÓN ---
            while ($keepFetching) {
                
                $params = [
                    'per_page' => 100,
                    'page' => $page
                ];

                if ($searchTerm) {
                    $params['search'] = $searchTerm;
                } else {
                    $params['role'] = 'all';
                }

                // Pedimos los datos a la API
                $customers = $woocommerce->get('customers', $params);

                // Si no hay resultados en esta página, terminamos el bucle
                if (empty($customers)) {
                    $keepFetching = false;
                    break;
                }

                foreach ($customers as $customer) {
                    if (!empty($customer->email)) {
                        
                        $fullName = trim($customer->first_name . ' ' . $customer->last_name);
                        if (empty($fullName)) {
                            $fullName = $customer->username;
                        }

                        $phone = $customer->billing->phone ?? '';

                        Client::updateOrCreate(
                            ['email' => $customer->email],
                            [
                                'name' => $fullName,
                                'phone' => $phone,
                            ]
                        );
                        $count++;
                    }
                }

                // Si es una búsqueda específica, probablemente no necesitemos paginar más allá de 100
                // pero si es masivo, seguimos a la siguiente página.
                if ($searchTerm) {
                    $keepFetching = false; // En búsquedas, paramos tras la primera página de resultados
                } else {
                    // Si recibimos menos de 100, significa que era la última página
                    if (count($customers) < 100) {
                        $keepFetching = false;
                    } else {
                        $page++; // Siguiente página
                    }
                }
            }

            Notification::make()
                ->title('Proceso Finalizado')
                ->body("Se han sincronizado un total de {$count} clientes.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error de API')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}