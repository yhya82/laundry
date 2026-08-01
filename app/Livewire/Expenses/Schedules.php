<?php

namespace App\Livewire\Expenses;

use App\Models\ExpenseCategory;
use App\Models\ExpenseSchedule;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Schedules extends Component
{
    use WithPagination;

    public bool $showDrawer = false;

    public ?ExpenseSchedule $editing = null;

    public string $title = '';

    public ?int $category_id = null;

    public string $amount = '';

    public string $frequency = 'monthly';

    public string $next_run_date = '';

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'next_run_date' => ['required', 'date'],
        ];
    }

    #[Computed]
    public function categories()
    {
        return ExpenseCategory::orderBy('name')->get();
    }

    public function create(): void
    {
        $this->reset(['editing', 'title', 'category_id', 'amount']);
        $this->frequency = 'monthly';
        $this->next_run_date = now()->toDateString();
        $this->showDrawer = true;
    }

    public function edit(ExpenseSchedule $schedule): void
    {
        $this->editing = $schedule;
        $this->title = $schedule->title;
        $this->category_id = $schedule->category_id;
        $this->amount = (string) $schedule->amount;
        $this->frequency = $schedule->frequency;
        $this->next_run_date = $schedule->next_run_date->toDateString();
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('notify', type: 'success', message: 'Schedule updated.');
        } else {
            ExpenseSchedule::create([...$data, 'status' => 'active', 'created_by' => Auth::id()]);
            $this->dispatch('notify', type: 'success', message: 'Schedule created.');
        }

        $this->closeDrawer();
    }

    public function togglePause(ExpenseSchedule $schedule): void
    {
        $schedule->update(['status' => $schedule->status === 'active' ? 'paused' : 'active']);
        $this->dispatch('notify', type: 'success', message: "Schedule marked {$schedule->status}.");
    }

    public function cancelSchedule(ExpenseSchedule $schedule): void
    {
        $schedule->update(['status' => 'cancelled']);
        $this->dispatch('notify', type: 'warning', message: 'Schedule cancelled.');
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['editing', 'title', 'category_id', 'amount']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.expenses.schedules', [
            'schedules' => ExpenseSchedule::query()
                ->with('category')
                ->orderBy('next_run_date')
                ->paginate(15),
        ])->title('Expense Schedules');
    }
}
