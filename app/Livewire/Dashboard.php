<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Placeholder landing page — proves the shell (auth, layout, permissions)
 * works end-to-end. Real dashboard content (cards, charts, activity) is
 * Phase 13's job.
 */
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard.index')->title('Dashboard');
    }
}
