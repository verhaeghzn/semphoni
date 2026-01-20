<?php

namespace App\Models;

use App\Enums\ActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Command extends Model
{
    /** @use HasFactory<\Database\Factories\CommandFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'action_type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ActionType::class,
        ];
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class)->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ClientLog::class);
    }

    public function permissionName(): string
    {
        return 'command.execute.'.$this->name;
    }
}
