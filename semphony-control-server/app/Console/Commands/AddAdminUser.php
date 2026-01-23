<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AddAdminUser extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:add-admin {--email= : Email address of the admin user}';

    /**
     * @var string
     */
    protected $description = 'Interactively create or promote an admin user';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?? '');

        if ($email === '') {
            $email = (string) $this->ask('Email');
        }

        $email = mb_strtolower(trim($email));

        while (($error = $this->validateWithRules($email, ['required', 'email'])) !== null) {
            $this->error($error);
            $email = mb_strtolower(trim((string) $this->ask('Email')));
        }

        /** @var User|null $existingUser */
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            $confirmed = $this->confirm(
                "User {$existingUser->name} <{$existingUser->email}> already exists. Assign the Admin role?",
                true,
            );

            if (! $confirmed) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }

            $this->ensureAdminRoleExists();

            $existingUser->syncRoles(['Admin']);

            $this->info("User {$existingUser->email} is now an Admin.");

            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Name'));

        while (($error = $this->validateWithRules($name, ['required', 'string', 'min:2'])) !== null) {
            $this->error($error);
            $name = trim((string) $this->ask('Name'));
        }

        $passwordValue = (string) $this->secret('Password');

        while (($error = $this->validateWithRules($passwordValue, ['required', 'string', Password::default()], field: 'password')) !== null) {
            $this->error($error);
            $passwordValue = (string) $this->secret('Password');
        }

        $passwordConfirmation = (string) $this->secret('Confirm password');

        while ($passwordConfirmation !== $passwordValue) {
            $this->error('Passwords do not match.');
            $passwordConfirmation = (string) $this->secret('Confirm password');
        }

        $emailVerified = $this->confirm('Mark email as verified?', true);

        $this->ensureAdminRoleExists();

        /** @var User $user */
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $passwordValue,
        ]);

        $user->forceFill([
            'email_verified_at' => $emailVerified ? now() : null,
        ])->save();

        $user->syncRoles(['Admin']);

        $this->info("Admin user created: {$user->name} <{$user->email}>");

        return self::SUCCESS;
    }

    private function ensureAdminRoleExists(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function validateWithRules(string $value, array $rules, string $field = 'value'): ?string
    {
        $validator = Validator::make(
            [$field => $value],
            [$field => $rules],
        );

        if ($validator->fails()) {
            return $validator->errors()->first($field);
        }

        return null;
    }
}

