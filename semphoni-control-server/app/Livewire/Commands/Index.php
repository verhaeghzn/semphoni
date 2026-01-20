<?php

namespace App\Livewire\Commands;

use App\Models\Command;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    public function render(): View
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        /** @var Collection<string, list<string>> $permissionToRoles */
        $permissionToRoles = $roles->reduce(function (Collection $carry, Role $role): Collection {
            foreach ($role->permissions->pluck('name') as $permissionName) {
                $carry->put($permissionName, array_values(array_unique([
                    ...($carry->get($permissionName, [])),
                    $role->name,
                ])));
            }

            return $carry;
        }, collect());

        return view('livewire.commands.index', [
            'commands' => Command::query()->orderBy('name')->get(),
            'permissionToRoles' => $permissionToRoles,
        ]);
    }
}
