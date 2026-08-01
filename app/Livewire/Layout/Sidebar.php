<?php

namespace App\Livewire\Layout;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Sidebar extends Component
{
    public string $businessName = '';

    public function mount(): void
    {
        $this->businessName = $this->currentBusinessName();
    }

    /**
     * Listens on the public `branding` channel — proves Reverb delivers a
     * no-refresh update per §3.2 ("if an Administrator changes the business
     * name/logo, the sidebar... updates instantly via Laravel Reverb").
     */
    #[On('echo:branding,BrandingUpdated')]
    public function refreshBranding(): void
    {
        $this->businessName = $this->currentBusinessName();
    }

    private function currentBusinessName(): string
    {
        return Setting::where('setting_group', 'general')
            ->where('setting_key', 'business_name')
            ->value('setting_value') ?? config('app.name');
    }

    /**
     * @return array<int, array{label: string, route: string, permission: ?string, icon: string}>
     */
    public function navItems(): array
    {
        $user = Auth::user();

        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'icon' => 'grid'],
            ['label' => 'Customers', 'route' => 'customers.index', 'permission' => 'customers.view', 'icon' => 'users'],
            ['label' => 'Laundry Orders', 'route' => 'orders.index', 'permission' => 'orders.view', 'icon' => 'basket'],
            ['label' => 'Subscriptions', 'route' => 'subscriptions.index', 'permission' => 'subscriptions.view', 'icon' => 'repeat'],
            ['label' => 'Collections', 'route' => 'collections.index', 'permission' => 'collections.manage', 'icon' => 'truck'],
            ['label' => 'Packages', 'route' => 'packages.index', 'permission' => 'packages.view', 'icon' => 'box'],
            ['label' => 'Payments', 'route' => 'payments.index', 'permission' => 'payments.view', 'icon' => 'card'],
            ['label' => 'Damage Reports', 'route' => 'damage.index', 'permission' => 'damage.view', 'icon' => 'alert'],
            ['label' => 'Deliveries', 'route' => 'deliveries.index', 'permission' => 'deliveries.view', 'icon' => 'map'],
            ['label' => 'Employees', 'route' => 'employees.index', 'permission' => 'employees.view', 'icon' => 'id'],
            ['label' => 'Expenses', 'route' => 'expenses.index', 'permission' => 'expenses.view', 'icon' => 'receipt'],
            ['label' => 'Reports', 'route' => 'reports.index', 'permission' => 'reports.view', 'icon' => 'chart'],
            ['label' => 'Users', 'route' => 'users.index', 'permission' => 'users.manage', 'icon' => 'shield'],
            ['label' => 'Settings', 'route' => 'settings.index', 'permission' => 'settings.view', 'icon' => 'cog'],
        ];

        return array_values(array_filter(
            $items,
            fn ($item) => $item['permission'] === null || $user->hasPermission($item['permission'])
        ));
    }

    public function render()
    {
        return view('livewire.layout.sidebar', [
            'navItems' => $this->navItems(),
        ]);
    }
}
