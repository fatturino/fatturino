<div {{ $attributes->class([
    'grid gap-4 sm:grid-cols-2',
    '[&>label]:text-sm [&>label]:font-semibold [&>label]:text-content',
    '[&_input]:mt-1 [&_input]:h-11 [&_input]:w-full [&_input]:rounded-md [&_input]:border [&_input]:border-border [&_input]:bg-white [&_input]:px-3 [&_input]:text-sm',
    '[&_select]:mt-1 [&_select]:h-11 [&_select]:w-full [&_select]:rounded-md [&_select]:border [&_select]:border-border [&_select]:bg-white [&_select]:px-3 [&_select]:text-sm',
    '[&_textarea]:mt-1 [&_textarea]:w-full [&_textarea]:rounded-md [&_textarea]:border [&_textarea]:border-border [&_textarea]:bg-white [&_textarea]:px-3 [&_textarea]:py-2 [&_textarea]:text-sm',
    '[&_input:disabled]:cursor-not-allowed [&_input:disabled]:bg-surface-muted [&_input:disabled]:text-content-muted',
    '[&_select:disabled]:cursor-not-allowed [&_select:disabled]:bg-surface-muted [&_select:disabled]:text-content-muted',
]) }}>
    {{ $slot }}
</div>
