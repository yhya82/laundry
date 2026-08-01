<?php

namespace Tests\Feature\Settings;

use App\Events\BrandingUpdated;
use App\Events\SettingUpdated;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            SettingSeeder::class,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'administrator')->first()->id, ['is_primary' => true]);
    }

    public function test_updating_business_name_dispatches_branding_updated(): void
    {
        Event::fake([BrandingUpdated::class]);

        $setting = Setting::where('setting_group', 'general')->where('setting_key', 'business_name')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class)
            ->set("values.{$setting->id}", 'Sunrise Laundromat')
            ->call('save', $setting->id);

        Event::assertDispatched(BrandingUpdated::class, fn ($event) => $event->businessName === 'Sunrise Laundromat');

        $this->assertSame('Sunrise Laundromat', $setting->fresh()->setting_value);
    }

    public function test_updating_a_non_branding_setting_dispatches_the_generic_event(): void
    {
        Event::fake([SettingUpdated::class]);

        $setting = Setting::where('setting_group', 'laundry')->where('setting_key', 'max_processing_packages')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class)
            ->set("values.{$setting->id}", '20')
            ->call('save', $setting->id);

        Event::assertDispatched(SettingUpdated::class, fn ($event) => $event->key === 'max_processing_packages' && $event->value === '20');
    }

    public function test_integer_setting_rejects_non_numeric_value(): void
    {
        $setting = Setting::where('setting_group', 'laundry')->where('setting_key', 'max_processing_packages')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class)
            ->set("values.{$setting->id}", 'not-a-number')
            ->call('save', $setting->id)
            ->assertHasErrors(["values.{$setting->id}"]);
    }
}
