<?php

namespace App\Livewire\Settings;

use App\Events\BrandingUpdated;
use App\Events\SettingUpdated;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    /** @var array<int, array{id: int, setting_group: string, setting_key: string, value_type: string, description: ?string}> */
    public array $meta = [];

    /**
     * Keyed by setting id (int), not "group.key" — a dot-containing string
     * key collides with Livewire's own dot-notation for nested property
     * access (wire:model="values.general.business_name" would resolve as
     * $this->values['general']['business_name'], not the flat key this
     * array actually used — caught by a failing test, not by inspection).
     *
     * @var array<int, string>
     */
    public array $values = [];

    public function mount(): void
    {
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        $settings = Setting::orderBy('setting_group')->orderBy('setting_key')->get();

        $this->meta = $settings->map(fn ($s) => [
            'id' => $s->id,
            'setting_group' => $s->setting_group,
            'setting_key' => $s->setting_key,
            'value_type' => $s->value_type,
            'description' => $s->description,
        ])->all();

        $this->values = $settings->mapWithKeys(fn ($s) => [$s->id => $s->setting_value])->all();
    }

    public function groupedMeta()
    {
        return collect($this->meta)->groupBy('setting_group');
    }

    public function save(int $settingId): void
    {
        $setting = Setting::findOrFail($settingId);
        $value = $this->values[$settingId] ?? '';

        if ($setting->value_type === 'integer') {
            $this->validate(["values.{$settingId}" => ['required', 'integer']], [], ["values.{$settingId}" => $setting->setting_key]);
        } elseif ($setting->value_type === 'json') {
            $this->validate(["values.{$settingId}" => ['required']], [], ["values.{$settingId}" => $setting->setting_key]);
            if (json_decode($value) === null && $value !== 'null') {
                $this->addError("values.{$settingId}", 'Must be valid JSON.');

                return;
            }
        }

        $setting->update(['setting_value' => $value, 'updated_by' => Auth::id()]);

        if ($setting->setting_group === 'general' && $setting->setting_key === 'business_name') {
            BrandingUpdated::dispatch($value);
        } else {
            SettingUpdated::dispatch($setting->setting_group, $setting->setting_key, $value);
        }

        $this->dispatch('notify', type: 'success', message: ucfirst(str_replace('_', ' ', $setting->setting_key)).' updated.');
    }

    public function render()
    {
        return view('livewire.settings.index', [
            'grouped' => $this->groupedMeta(),
        ])->title('Settings');
    }
}
