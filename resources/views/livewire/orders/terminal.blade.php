<div>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Laundry Terminal</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Select a package, then add clothes to it — a package must exist before any item can be added.</p>
        </div>
        <a href="{{ route('orders.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">← Back to queue</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- Left pane: packages, then clothes for the active line --}}
        <div class="lg:col-span-3 space-y-6">
            <div>
                <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">1. Packages</h2>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($this->activePackages as $package)
                        <button
                            wire:click="addPackage({{ $package->id }})"
                            type="button"
                            class="rounded-lg border border-slate-200 p-3 text-left hover:border-sky-400 hover:bg-sky-50 dark:border-slate-700 dark:hover:bg-sky-950"
                        >
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $package->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($package->price, 2) }} · up to {{ $package->maximum_clothes }} items</p>
                            @if ($package->priority === 'express')
                                <span class="mt-1 inline-flex rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">Express</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">2. Clothes</h2>
                @error('cart') <p class="mb-2 text-sm text-rose-600">{{ $message }}</p> @enderror

                @if ($activeCartLine === null || ! isset($cart[$activeCartLine]))
                    <div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400 dark:border-slate-600">
                        Add a package above, or select one from the cart, before adding clothes.
                    </div>
                @else
                    <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                        Adding to <strong>{{ $cart[$activeCartLine]['package_name'] }}</strong> —
                        {{ $this->cartLineItemCount($activeCartLine) }}/{{ $cart[$activeCartLine]['maximum_clothes'] }} items
                    </p>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($this->activeClothingTypes as $type)
                            <button
                                wire:click="addClothingItem({{ $type->id }})"
                                type="button"
                                class="rounded-lg border border-slate-200 p-2.5 text-center text-sm hover:border-sky-400 hover:bg-sky-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-sky-950"
                            >
                                {{ $type->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Right pane: cart, customer, discount, payment --}}
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Cart</h2>
                @if (empty($cart))
                    <p class="text-sm text-slate-400">No packages added yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($cart as $index => $line)
                            <div
                                wire:click="setActiveCartLine({{ $index }})"
                                @class([
                                    'cursor-pointer rounded-md border p-3 text-sm',
                                    'border-sky-400 bg-sky-50 dark:bg-sky-950' => $activeCartLine === $index,
                                    'border-slate-200 dark:border-slate-700' => $activeCartLine !== $index,
                                ])
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ $line['package_name'] }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($line['unit_price'], 2) }}</span>
                                        <button wire:click.stop="removePackageLine({{ $index }})" class="text-rose-500 hover:text-rose-700">×</button>
                                    </div>
                                </div>
                                @if (! empty($line['items']))
                                    <ul class="mt-1.5 space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        @foreach ($line['items'] as $itemIndex => $item)
                                            <li class="flex items-center justify-between">
                                                <span>{{ $item['clothing_type_name'] }} × {{ $item['quantity'] }}</span>
                                                <button wire:click.stop="decrementClothingItem({{ $index }}, {{ $itemIndex }})" class="text-slate-400 hover:text-rose-600">−</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1 text-xs text-slate-400">No items yet</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3">
                    <textarea wire:model="instructions" rows="2" placeholder="Special instructions..." class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Customer</h2>
                @error('customer') <p class="mb-2 text-sm text-rose-600">{{ $message }}</p> @enderror

                @if ($this->selectedCustomer)
                    <div class="flex items-center justify-between rounded-md bg-slate-50 p-2.5 text-sm dark:bg-slate-800">
                        <div>
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ $this->selectedCustomer->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $this->selectedCustomer->phone }}</p>
                        </div>
                        <button wire:click="clearCustomer" class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400">Change</button>
                    </div>
                @elseif ($showNewCustomerForm)
                    <div class="space-y-2">
                        <input type="text" wire:model="newCustomerName" placeholder="Name" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        @error('newCustomerName') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                        <input type="text" wire:model="newCustomerPhone" placeholder="Phone" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        @error('newCustomerPhone') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                        <div class="flex gap-2">
                            <button wire:click="createQuickCustomer" type="button" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700">Add customer</button>
                            <button wire:click="$set('showNewCustomerForm', false)" type="button" class="rounded-md px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">Cancel</button>
                        </div>
                    </div>
                @else
                    <input type="search" wire:model.live.debounce.300ms="customerSearch" placeholder="Search name or phone..." class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    @if ($this->customerResults->isNotEmpty())
                        <div class="mt-1 max-h-40 overflow-y-auto rounded-md border border-slate-200 dark:border-slate-700">
                            @foreach ($this->customerResults as $customer)
                                <button wire:click="selectCustomer({{ $customer->id }})" type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                    {{ $customer->name }} <span class="text-slate-400">· {{ $customer->phone }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <button wire:click="$set('showNewCustomerForm', true)" type="button" class="mt-2 text-sm font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">+ New customer</button>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Discount</h2>
                <select wire:model.live="discountTemplateId" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">No discount</option>
                    @foreach ($this->activeDiscountTemplates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->discount_type === 'percentage' ? $template->value.'%' : number_format($template->value, 2) }})</option>
                    @endforeach
                </select>

                @if (! $discountTemplateId)
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model.live="useCustomDiscount" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        Custom discount
                    </label>

                    @if ($useCustomDiscount)
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <select wire:model.live="customDiscountType" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                <option value="percentage">%</option>
                                <option value="fixed">Fixed</option>
                            </select>
                            <input type="number" step="0.01" min="0" wire:model.live="customDiscountValue" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        </div>
                        <input type="text" wire:model="discountReason" placeholder="Reason (required)" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        @error('discountReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @endif
                @endif
                @error('discount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <div class="flex justify-between text-sm text-slate-600 dark:text-slate-300">
                    <span>Subtotal</span><span class="tabular-nums">{{ number_format($this->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm text-slate-600 dark:text-slate-300">
                    <span>Discount</span><span class="tabular-nums">−{{ number_format($this->discountPreview, 2) }}</span>
                </div>
                <div class="mt-1 flex justify-between border-t border-slate-200 pt-1 text-base font-semibold text-slate-900 dark:border-slate-700 dark:text-white">
                    <span>Total</span><span class="tabular-nums">{{ number_format($this->total, 2) }}</span>
                </div>

                <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" wire:model.live="payNow" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    Pay now
                </label>

                @if ($payNow)
                    <div class="mt-2 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <select wire:model="paymentMethod" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile money</option>
                                <option value="bank_transfer">Bank transfer</option>
                            </select>
                            <input type="number" step="0.01" min="0" wire:model="paymentAmount" placeholder="Amount" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        </div>
                        <button wire:click="$set('paymentAmount', '{{ $this->total }}')" type="button" class="text-xs font-medium text-sky-600 hover:text-sky-800 dark:text-sky-400">Fill full amount</button>
                    </div>
                @endif

                <button
                    wire:click="completeOrder"
                    type="button"
                    class="mt-4 w-full rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    Complete order
                </button>
            </div>
        </div>
    </div>
</div>
