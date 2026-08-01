<?php

namespace App\Livewire\Damage;

use App\Models\DamageReport;
use App\Services\DamageService;
use App\Services\StoreCreditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

class Show extends Component
{
    use WithFileUploads;

    public DamageReport $report;

    public bool $showResolveDrawer = false;

    public string $resolutionType = 'repair';

    public string $cashAmount = '0.00';

    public string $creditAmount = '0.00';

    /** @var array<int, TemporaryUploadedFile> */
    public array $newEvidence = [];

    public function mount(DamageReport $report): void
    {
        $this->report = $report->load(['laundryOrder', 'customer', 'items.damageType', 'items.orderItem.clothingType', 'evidence', 'createdBy', 'approvedBy']);
    }

    private function refresh(): void
    {
        $this->report = $this->report->fresh(['laundryOrder', 'customer', 'items.damageType', 'items.orderItem.clothingType', 'evidence', 'createdBy', 'approvedBy']);
    }

    public function markUnderReview(DamageService $service): void
    {
        try {
            $service->markUnderReview($this->report);
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->refresh();
        $this->dispatch('notify', type: 'success', message: 'Marked under review.');
    }

    public function openResolveDrawer(): void
    {
        $this->resolutionType = $this->report->resolution_type ?? 'repair';
        $this->cashAmount = (string) $this->report->cash_compensation_amount;
        $this->creditAmount = (string) $this->report->store_credit_compensation_amount;
        $this->showResolveDrawer = true;
    }

    public function approve(DamageService $service): void
    {
        $this->validate([
            'resolutionType' => ['required', 'in:repair,refund,rewash,store_credit,replacement,other'],
            'cashAmount' => ['required', 'numeric', 'min:0'],
            'creditAmount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $service->approve($this->report, $this->resolutionType, $this->cashAmount, $this->creditAmount, Auth::user());
        } catch (ValidationException $e) {
            $this->addError('cashAmount', $e->validator->errors()->first());

            return;
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->refresh();
        $this->showResolveDrawer = false;
        $this->dispatch('notify', type: 'success', message: 'Resolution plan set — resolve it to disburse compensation.');
    }

    public function resolve(DamageService $service, StoreCreditService $storeCreditService): void
    {
        try {
            $service->resolve($this->report, Auth::user(), $storeCreditService);
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->refresh();
        $this->dispatch('notify', type: 'success', message: 'Damage report resolved.');
    }

    public function reject(DamageService $service): void
    {
        try {
            $service->reject($this->report, Auth::user());
        } catch (RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->refresh();
        $this->dispatch('notify', type: 'warning', message: 'Damage report rejected.');
    }

    public function addEvidence(DamageService $service): void
    {
        $this->validate(['newEvidence.*' => ['required', 'image', 'max:5120']]);

        foreach ($this->newEvidence as $file) {
            $service->addEvidence($this->report, $file, Auth::user());
        }

        $this->newEvidence = [];
        $this->refresh();
        $this->dispatch('notify', type: 'success', message: 'Evidence uploaded.');
    }

    public function deleteEvidence(int $evidenceId, DamageService $service): void
    {
        $evidence = $this->report->evidence->firstWhere('id', $evidenceId);

        if ($evidence) {
            $service->deleteEvidence($evidence);
            $this->refresh();
        }
    }

    public function render()
    {
        return view('livewire.damage.show')->title("Damage report — {$this->report->laundryOrder->order_number}");
    }
}
