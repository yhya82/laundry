<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $categoryFilter = '';

    public bool $showCreateDrawer = false;

    public string $title = '';

    public ?int $category_id = null;

    public string $amount = '';

    public string $payment_method = 'cash';

    public ?string $description = null;

    public string $expense_date = '';

    public ?TemporaryUploadedFile $attachment = null;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank_transfer,mobile_money,card'],
            'description' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ];
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function categories()
    {
        return ExpenseCategory::orderBy('name')->get();
    }

    public function openCreateDrawer(): void
    {
        $this->reset(['title', 'category_id', 'amount', 'description', 'attachment']);
        $this->payment_method = 'cash';
        $this->expense_date = now()->toDateString();
        $this->showCreateDrawer = true;
    }

    public function save(ExpenseService $service): void
    {
        $data = $this->validate();

        $attachmentPath = $this->attachment
            ? $this->attachment->store('expense-attachments', 'public')
            : null;

        unset($data['attachment']);

        $service->createExpense([...$data, 'attachment_path' => $attachmentPath], Auth::user());

        $this->showCreateDrawer = false;
        $this->dispatch('notify', type: 'success', message: 'Expense recorded.');
    }

    public function approve(int $expenseId, ExpenseService $service): void
    {
        $this->applyStatusChange($service, $expenseId, fn ($expense) => $service->approve($expense, Auth::user()), 'Expense approved.');
    }

    public function reject(int $expenseId, ExpenseService $service): void
    {
        $this->applyStatusChange($service, $expenseId, fn ($expense) => $service->reject($expense, Auth::user()), 'Expense rejected.');
    }

    public function markPaid(int $expenseId, ExpenseService $service): void
    {
        $this->applyStatusChange($service, $expenseId, fn ($expense) => $service->markPaid($expense), 'Expense marked paid.');
    }

    public function cancel(int $expenseId, ExpenseService $service): void
    {
        $this->applyStatusChange($service, $expenseId, fn ($expense) => $service->cancel($expense), 'Expense cancelled.');
    }

    private function applyStatusChange(ExpenseService $service, int $expenseId, callable $action, string $message): void
    {
        try {
            $action(Expense::findOrFail($expenseId));
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('notify', type: 'success', message: $message);
    }

    public function attachmentUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function render()
    {
        return view('livewire.expenses.index', [
            'expenses' => Expense::query()
                ->with(['category', 'createdBy', 'approvedBy'])
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
                ->latest('expense_date')
                ->paginate(20),
        ])->title('Expenses');
    }
}
