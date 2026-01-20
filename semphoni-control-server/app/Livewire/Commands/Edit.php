<?php

namespace App\Livewire\Commands;

use App\Models\Command;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Edit extends Component
{
    public int $commandId;

    public string $name = '';

    public string $actionType = '';

    public ?string $description = null;

    /**
     * @var array<string, bool>
     */
    public array $roleAccess = [];

    /**
     * @var Collection<int, Role>
     */
    public Collection $roles;

    public function mount(Command $command): void
    {
        $this->commandId = $command->id;
        $this->name = $command->name;
        $this->actionType = $command->action_type->value;
        $this->description = $command->description;

        $this->roles = Role::query()->orderBy('name')->get();

        $permissionName = $command->permissionName();

        foreach ($this->roles as $role) {
            $this->roleAccess[$role->name] = $role->hasPermissionTo($permissionName);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'description' => ['nullable', 'string'],
            'roleAccess' => ['array'],
        ]);

        $command = Command::query()->findOrFail($this->commandId);
        $command->update([
            'description' => $validated['description'],
        ]);

        $permission = Permission::query()->firstOrCreate([
            'name' => $command->permissionName(),
            'guard_name' => 'web',
        ]);

        foreach (Role::query()->whereIn('name', array_keys($this->roleAccess))->get() as $role) {
            if (($this->roleAccess[$role->name] ?? false) === true) {
                $role->givePermissionTo($permission);
            } else {
                $role->revokePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('status', __('Command updated.'));

        $this->redirect(route('commands.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.commands.edit');
    }
}
