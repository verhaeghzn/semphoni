<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\CarbonInterface;
use App\Enums\ActionType;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'system_id',
        'name',
        'api_key',
        'width_px',
        'height_px',
        'can_screenshot',
        'last_screenshot_png_base64',
        'last_screenshot_taken_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'can_screenshot' => 'bool',
            'last_screenshot_taken_at' => 'datetime',
            'is_active' => 'bool',
        ];
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function commands(): BelongsToMany
    {
        return $this->belongsToMany(Command::class)->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ClientLog::class);
    }

    public function latestLog(): HasOne
    {
        return $this->hasOne(ClientLog::class)->latestOfMany();
    }

    public function latestNonHeartbeatLog(): HasOne
    {
        return $this->hasOne(ClientLog::class)
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('command', function ($commandQuery): void {
                        $commandQuery->where('action_type', ActionType::Heartbeat);
                    })
                    ->where('summary', '!=', 'Heartbeat');
            })
            ->latestOfMany();
    }

    public function isActive(int $seconds = 20): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        $lastActivityAt = $this->latestLog?->created_at;

        if (! $lastActivityAt instanceof CarbonInterface) {
            return false;
        }

        return $lastActivityAt->greaterThanOrEqualTo(now()->subSeconds($seconds));
    }
}
