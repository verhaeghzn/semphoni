<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientType extends Model
{
    /** @use HasFactory<\Database\Factories\ClientTypeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function commands(): BelongsToMany
    {
        return $this->belongsToMany(Command::class)->withTimestamps();
    }
}
