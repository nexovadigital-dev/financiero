<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Filament\Pages\ApiSettings;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Automattic\WooCommerce\Client as WooClient;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Livewire\Attributes\On;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    // Propiedades para el progreso
    public bool $isImporting = false;
    public int $importProgress = 0;
    public int $importTotal = 0;
    public int $importedCount = 0;
    public string $importStatus = '';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Cliente Local'),

            Actions\ActionGroup::make([
                // 1. IMPORTACIÓN MASIVA CON PROGRESO
                Actions\Action::make('syncAll')
                    ->label('Importar Todos')
                    ->icon('heroicon-o-users')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Importación Completa')
                    ->modalDescription('El sistema recorrerá página por página toda tu tienda WooCommerce para descargar absolutamente todos los clientes. Verás el progreso en tiempo real.')
                    ->action(fn () => $this->syncWooClients(null)),

                // 2. BUSCADOR ESPECÍFICO
                Actions\Action::make('searchWoo')
                    ->label('Buscar Específico')
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
            ->label('WooCommerce')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->button(),
        ];
    }

    public function syncWooClients($searchTerm = null)
    {
        $wooUrl = ApiSettings::getWooUrl();
        $wooKey = ApiSettings::getWooKey();
        $wooSecret = ApiSettings::getWooSecret();

        if (!$wooUrl || !$wooKey || !$wooSecret) {
            Notification::make()
                ->title('Error: Faltan credenciales de WooCommerce')
                ->body('Configura las credenciales en Configuración > Configuración API')
                ->danger()
                ->send();
            return;
        }

        try {
            $woocommerce = new WooClient(
                $wooUrl,
                $wooKey,
                $wooSecret,
                ['version' => 'wc/v3', 'verify_ssl' => false, 'timeout' => 60]
            );

            // Iniciar estado de importación
            $this->isImporting = true;
            $this->importProgress = 0;
            $this->importedCount = 0;
            $this->importStatus = 'Obteniendo total de clientes...';

            // Obtener el total de clientes primero
            $totalResponse = $woocommerce->get('customers', ['per_page' => 1, 'role' => 'all']);

            // WooCommerce devuelve el total en los headers, pero como fallback contamos páginas
            $this->importTotal = 0;

            // Notificación de inicio
            Notification::make()
                ->title('Iniciando importación')
                ->body('Procesando clientes de WooCommerce...')
                ->info()
                ->send();

            $page = 1;
            $count = 0;
            $keepFetching = true;

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

                $this->importStatus = "Descargando página {$page}...";

                // Pedimos los datos a la API
                $customers = $woocommerce->get('customers', $params);

                // Si no hay resultados en esta página, terminamos el bucle
                if (empty($customers)) {
                    $keepFetching = false;
                    break;
                }

                $customersInPage = count($customers);
                $processedInPage = 0;

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
                        $processedInPage++;
                    }
                }

                $this->importedCount = $count;
                $this->importStatus = "Página {$page} completada: {$count} clientes importados";

                // Notificación de progreso cada 500 clientes
                if ($count % 500 === 0 && $count > 0) {
                    Notification::make()
                        ->title("Progreso: {$count} clientes")
                        ->body("Página {$page} procesada...")
                        ->info()
                        ->duration(2000)
                        ->send();
                }

                if ($searchTerm) {
                    $keepFetching = false;
                } else {
                    if (count($customers) < 100) {
                        $keepFetching = false;
                    } else {
                        $page++;
                    }
                }
            }

            // Finalizar
            $this->isImporting = false;
            $this->importProgress = 100;
            $this->importStatus = "Completado: {$count} clientes importados";

            Notification::make()
                ->title('¡Importación Completada!')
                ->body("Se han sincronizado {$count} clientes de WooCommerce correctamente.")
                ->success()
                ->persistent()
                ->send();

            // Refrescar la tabla
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            $this->isImporting = false;
            $this->importStatus = 'Error: ' . $e->getMessage();

            Notification::make()
                ->title('Error de API')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    // Vista personalizada con barra de progreso
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}