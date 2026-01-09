<div>
@if($connected ?? false)
    <span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 bg-green-50 dark:bg-green-500/10 dark:ring-green-500/20">
        <svg class="h-1.5 w-1.5 fill-green-500" viewBox="0 0 6 6">
            <circle cx="3" cy="3" r="3" />
        </svg>
        {{ $label ?? 'Estado' }}
    </span>
@else
    <span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 bg-gray-50 dark:bg-gray-400/10 dark:ring-gray-400/20 dark:text-gray-400">
        <svg class="h-1.5 w-1.5 fill-gray-400" viewBox="0 0 6 6">
            <circle cx="3" cy="3" r="3" />
        </svg>
        {{ $label ?? 'No Configurada' }}
    </span>
@endif
</div>
