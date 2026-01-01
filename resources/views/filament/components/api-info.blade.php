@if($connected)
    <div class="rounded-lg bg-success-50 dark:bg-success-500/10 p-4 mb-4">
        <p class="text-sm font-semibold text-success-700 dark:text-success-400 mb-3">
            ✅ API Configurada
        </p>

        <div class="space-y-2">
            @foreach($items as $item)
                <div class="flex items-start gap-2 text-sm">
                    <span class="text-base">{{ $item['icon'] }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300 min-w-[140px]">{{ $item['label'] }}:</span>
                    <span class="text-gray-600 dark:text-gray-400 break-all">{{ $item['value'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="rounded-lg bg-warning-50 dark:bg-warning-500/10 p-4 mb-4">
        <p class="text-sm text-warning-700 dark:text-warning-400">
            ⚠️ API no configurada. Complete los campos a continuación para conectar.
        </p>
    </div>
@endif
