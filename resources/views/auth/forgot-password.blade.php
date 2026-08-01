<x-layouts.guest title="Reset password">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <p class="text-xl font-semibold text-slate-900 dark:text-white">Reset your password</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">We'll email you a link to choose a new one.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            @session('status')
                <div class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    {{ $value }}
                </div>
            @endsession

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
                >
                    Email reset link
                </button>
            </form>

            <p class="mt-4 text-center text-sm">
                <a href="{{ route('login') }}" class="font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400">Back to sign in</a>
            </p>
        </div>
    </div>
</x-layouts.guest>
