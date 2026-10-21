<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\UpdateGroup;
use App\Exceptions\ConfirmationRequiredException;
use App\Http\Requests\StoreHuiGroupRequest;
use App\Http\Requests\UpdateHuiGroupRequest;
use App\Livewire\Concerns\SuggestsGroupAmounts;
use App\Models\HuiGroup;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Thêm dây (kèm các tay của mình) hoặc sửa thông tin dây (FR-01, FR-02, FR-12).
 * Mệnh giá, số tay và ngày bắt đầu chỉ nhập khi tạo.
 */
#[Layout('layouts.app')]
class GroupForm extends Component
{
    use SuggestsGroupAmounts;

    #[Locked]
    public ?HuiGroup $group = null;

    public string $name = '';

    public ?int $faceValue = null;

    public ?int $totalSlots = null;

    public string $startDate = '';

    public int $dueOffsetDays = 0;

    public string $hostName = '';

    public string $hostPhone = '';

    public int $commissionAmount = 0;

    public string $note = '';

    /** @var list<string> */
    public array $slotLabels = ['Tay 1'];

    public ?string $confirmationMessage = null;

    public function mount(?HuiGroup $group = null): void
    {
        if ($group?->exists) {
            Gate::authorize('update', $group);
            $this->group = $group;
            $this->fill([
                'name' => $group->name,
                'faceValue' => $group->face_value,
                'totalSlots' => $group->total_slots,
                'startDate' => $group->start_date->toDateString(),
                'dueOffsetDays' => $group->due_offset_days,
                'hostName' => $group->host_name,
                'hostPhone' => (string) $group->host_phone,
                'commissionAmount' => $group->commission_amount,
                'note' => (string) $group->note,
            ]);

            return;
        }

        Gate::authorize('create', HuiGroup::class);
        $this->startDate = now()->toDateString();
    }

    public function addSlotLabel(): void
    {
        $this->slotLabels[] = 'Tay '.(count($this->slotLabels) + 1);
    }

    public function removeSlotLabel(int $index): void
    {
        unset($this->slotLabels[$index]);
        $this->slotLabels = array_values($this->slotLabels);
    }

    public function save(CreateGroup $createGroup, UpdateGroup $updateGroup, bool $confirmed = false): void
    {
        if ($this->group !== null) {
            $this->update($updateGroup, $confirmed);

            return;
        }

        Gate::authorize('create', HuiGroup::class);

        $data = [
            'name' => trim($this->name),
            'face_value' => $this->faceValue,
            'total_slots' => $this->totalSlots,
            'start_date' => $this->startDate,
            'due_offset_days' => $this->dueOffsetDays,
            'host_name' => trim($this->hostName),
            'host_phone' => $this->nullIfBlank($this->hostPhone),
            'commission_amount' => $this->commissionAmount,
            'note' => $this->nullIfBlank($this->note),
            'slots' => array_map('trim', $this->slotLabels),
        ];

        $validated = StoreHuiGroupRequest::makeValidator($data)->validate();
        $slotLabels = $validated['slots'];
        unset($validated['slots']);

        $group = $createGroup->handle($validated, $slotLabels);

        session()->flash('status', 'Đã tạo dây hụi và lịch '.$group->total_slots.' kỳ.');
        $this->redirectRoute('groups.show', $group, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.groups.group-form', $this->groupAmountSuggestions())
            ->title($this->group === null ? 'Thêm dây hụi' : 'Sửa dây hụi');
    }

    private function update(UpdateGroup $updateGroup, bool $confirmed): void
    {
        /** @var HuiGroup $group */
        $group = $this->group;
        Gate::authorize('update', $group);

        $validated = UpdateHuiGroupRequest::makeValidator([
            'name' => trim($this->name),
            'due_offset_days' => $this->dueOffsetDays,
            'host_name' => trim($this->hostName),
            'host_phone' => $this->nullIfBlank($this->hostPhone),
            'commission_amount' => $this->commissionAmount,
            'note' => $this->nullIfBlank($this->note),
        ], $group)->validate();

        try {
            $updateGroup->handle($group, $validated, $confirmed);
        } catch (ConfirmationRequiredException $exception) {
            $this->confirmationMessage = $exception->getMessage();

            return;
        }

        session()->flash('status', 'Đã lưu thông tin dây.');
        $this->redirectRoute('groups.show', $group, navigate: true);
    }

    private function nullIfBlank(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
