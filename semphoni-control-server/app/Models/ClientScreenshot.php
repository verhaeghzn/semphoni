<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientScreenshot extends Model
{
    /** @use HasFactory<\Database\Factories\ClientScreenshotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'monitor_nr',
        'mime',
        'storage_disk',
        'storage_path',
        'bytes',
        'sha256',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'monitor_nr' => 'int',
            'bytes' => 'int',
            'taken_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
