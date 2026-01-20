<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class System extends Model
{
    /** @use HasFactory<\Database\Factories\SystemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'control_locked_until' => 'datetime',
        ];
    }

    public function controlLockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'control_locked_by_user_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
