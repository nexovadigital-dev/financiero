<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" color="success" icon="heroicon-o-check-circle">
                Guardar Configuración
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />

    {{-- Advertencia de seguridad --}}
    <div class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                🔒 Seguridad
            </x-slot>

            <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                <p>
                    ✓ <strong>Todas las claves y secretos se guardan encriptados</strong> en la base de datos usando el sistema de encriptación de Laravel.
                </p>
                <p>
                    ✓ Los valores encriptados están marcados con el icono 🔐 y requieren la clave de aplicación (APP_KEY) para ser desencriptados.
                </p>
                <p>
                    ⚠️ <strong>Importante:</strong> Mantén segura tu clave APP_KEY del archivo .env. Si la pierdes, no podrás recuperar las credenciales encriptadas.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
