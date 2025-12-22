<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{-- ADVERTENCIAS LEGALES Y DE SEGURIDAD --}}
    <div class="mt-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-bold text-red-800 dark:text-red-300 mb-2">
                    ⚠️ ADVERTENCIA - SISTEMA PRIVADO
                </h3>
                <div class="text-xs text-red-700 dark:text-red-400 space-y-1">
                    <p>• Este sistema es de uso EXCLUSIVO para personal autorizado.</p>
                    <p>• Todos los accesos son MONITOREADOS y REGISTRADOS.</p>
                    <p>• El acceso no autorizado está PROHIBIDO y constituye una violación de la ley.</p>
                    <p>• Cualquier intento de acceso ilegal será reportado a las autoridades competentes.</p>
                    <p>• Los infractores serán procesados en la máxima extensión de la ley.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- MENSAJE ADICIONAL --}}
    <div class="mt-4 text-center">
        <p class="text-xs text-gray-600 dark:text-gray-400">
            Si no tiene autorización para acceder a este sistema, <strong>salga inmediatamente</strong>.
        </p>
    </div>
</x-filament-panels::page.simple>
