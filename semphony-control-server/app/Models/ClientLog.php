<?php

namespace App\Models;

use App\Enums\LogDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLog extends Model
{
    /** @use HasFactory<\Database\Factories\ClientLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'system_id',
        'direction',
        'command_id',
        'summary',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'direction' => LogDirection::class,
            'payload' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
