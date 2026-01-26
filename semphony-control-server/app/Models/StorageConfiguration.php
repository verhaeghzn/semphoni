<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageConfiguration extends Model
{
    /** @use HasFactory<\Database\Factories\StorageConfigurationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clientFiles(): HasMany
    {
        return $this->hasMany(ClientFile::class);
    }
}
