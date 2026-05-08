<div>
    <x-header :title="__('app.wip.title')" separator />

    <div class="flex flex-col items-center justify-center h-[50vh] space-y-5">
        <x-icon name="o-code-bracket" class="w-20 h-20 text-gray-300" />
        <div class="text-2xl font-bold text-gray-400">{{ __('app.wip.message') }}</div>
        <x-button :label="__('app.wip.back_to_dashboard')" link="/dashboard" icon="o-home" variant="primary" />
    </div>
</div>
