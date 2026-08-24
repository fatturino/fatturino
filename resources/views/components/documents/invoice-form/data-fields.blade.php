@props(['variant' => 'default'])

@php
    if ($variant === 'sales-editor') {
        $fieldClasses = [
            'grid gap-4 sm:grid-cols-2 2xl:grid-cols-4',
            '[&>label]:text-sm [&>label]:font-medium [&>label]:text-content',
            '[&_input]:mt-1 [&_input]:h-11 [&_input]:w-full [&_input]:rounded-lg [&_input]:border [&_input]:border-border-strong [&_input]:bg-white [&_input]:px-3 [&_input]:text-sm [&_input]:focus:border-primary [&_input]:focus:outline-none [&_input]:focus:ring-2 [&_input]:focus:ring-primary/20',
            '[&_select]:mt-1 [&_select]:h-11 [&_select]:w-full [&_select]:rounded-lg [&_select]:border [&_select]:border-border-strong [&_select]:bg-white [&_select]:px-3 [&_select]:text-sm',
            '[&_input:disabled]:cursor-not-allowed [&_input:disabled]:bg-surface-muted [&_input:disabled]:text-content-muted',
            '[&_select:disabled]:cursor-not-allowed [&_select:disabled]:bg-surface-muted [&_select:disabled]:text-content-muted',
        ];
    } elseif ($variant === 'editor') {
        $fieldClasses = [
            'grid gap-4 sm:grid-cols-2 xl:grid-cols-4',
            '[&>label]:text-sm [&>label]:font-medium [&>label]:text-content',
            '[&_input]:mt-1 [&_input]:h-11 [&_input]:w-full [&_input]:rounded-lg [&_input]:border [&_input]:border-border-strong [&_input]:bg-white [&_input]:px-3 [&_input]:text-sm [&_input]:focus:border-primary [&_input]:focus:outline-none [&_input]:focus:ring-2 [&_input]:focus:ring-primary/20',
            '[&_select]:mt-1 [&_select]:h-11 [&_select]:w-full [&_select]:rounded-lg [&_select]:border [&_select]:border-border-strong [&_select]:bg-white [&_select]:px-3 [&_select]:text-sm',
            '[&_textarea]:mt-1 [&_textarea]:w-full [&_textarea]:rounded-lg [&_textarea]:border [&_textarea]:border-border-strong [&_textarea]:bg-white [&_textarea]:px-3 [&_textarea]:py-2 [&_textarea]:text-sm',
            '[&_input:disabled]:cursor-not-allowed [&_input:disabled]:bg-surface-muted [&_input:disabled]:text-content-muted',
            '[&_select:disabled]:cursor-not-allowed [&_select:disabled]:bg-surface-muted [&_select:disabled]:text-content-muted',
        ];
    } else {
        $fieldClasses = [
            'grid gap-4 sm:grid-cols-2',
            '[&>label]:text-sm [&>label]:font-semibold [&>label]:text-content',
            '[&_input]:mt-1 [&_input]:h-11 [&_input]:w-full [&_input]:rounded-md [&_input]:border [&_input]:border-border [&_input]:bg-white [&_input]:px-3 [&_input]:text-sm',
            '[&_select]:mt-1 [&_select]:h-11 [&_select]:w-full [&_select]:rounded-md [&_select]:border [&_select]:border-border [&_select]:bg-white [&_select]:px-3 [&_select]:text-sm',
            '[&_textarea]:mt-1 [&_textarea]:w-full [&_textarea]:rounded-md [&_textarea]:border [&_textarea]:border-border [&_textarea]:bg-white [&_textarea]:px-3 [&_textarea]:py-2 [&_textarea]:text-sm',
            '[&_input:disabled]:cursor-not-allowed [&_input:disabled]:bg-surface-muted [&_input:disabled]:text-content-muted',
            '[&_select:disabled]:cursor-not-allowed [&_select:disabled]:bg-surface-muted [&_select:disabled]:text-content-muted',
        ];
    }
@endphp

<div {{ $attributes->class([
    ...$fieldClasses,
]) }}>
    {{ $slot }}
</div>
