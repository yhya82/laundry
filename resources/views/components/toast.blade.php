<div
    x-data="{ show: false, type: 'success', message: '' }"
    @notify.window="
        type = $event.detail.type ?? 'success';
        message = $event.detail.message ?? '';
        show = true;
        setTimeout(() => show = false, 4000);
    "
    x-show="show"
    x-transition
    x-cloak
    class="fixed bottom-4 right-4 z-[60] flex max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
    :class="{
        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200': type === 'success',
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200': type === 'warning',
        'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200': type === 'error',
    }"
>
    <span x-text="message"></span>
</div>
