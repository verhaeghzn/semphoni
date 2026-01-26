<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFile extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'original_filename',
        'storage_type',
        'storage_configuration_id',
        'storage_path',
        'mime',
        'bytes',
        'sha256',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'int',
            'uploaded_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function storageConfiguration(): BelongsTo
    {
        return $this->belongsTo(StorageConfiguration::class);
    }
}
