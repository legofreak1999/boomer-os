<?php

namespace App\Models;

use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'label',
    'url',
    'interval_minutes',
    'check_type',
    'check_config',
    'notify_on',
    'enabled',
    'last_polled_at',
    'last_matched',
    'last_error',
    'consecutive_failures',
])]
class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    public const CHECK_TEXT_CONTAINS = 'text_contains';

    public const CHECK_CSS_SELECTOR = 'css_selector';

    public const CHECK_REGEX = 'regex';

    public const CHECK_HTTP_STATUS = 'http_status';

    public const CHECK_TYPES = [
        self::CHECK_TEXT_CONTAINS,
        self::CHECK_CSS_SELECTOR,
        self::CHECK_REGEX,
        self::CHECK_HTTP_STATUS,
    ];

    public const NOTIFY_ON_APPEARANCE = 'appearance';

    public const NOTIFY_ON_DISAPPEARANCE = 'disappearance';

    public const NOTIFY_ON_BOTH = 'both';

    public const NOTIFY_ON = [
        self::NOTIFY_ON_APPEARANCE,
        self::NOTIFY_ON_DISAPPEARANCE,
        self::NOTIFY_ON_BOTH,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_config' => 'array',
            'enabled' => 'boolean',
            'last_matched' => 'boolean',
            'last_polled_at' => 'datetime',
            'interval_minutes' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    public function notificationChannels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class);
    }

    public function isDue(): bool
    {
        if ($this->last_polled_at === null) {
            return true;
        }

        return $this->last_polled_at->addMinutes($this->interval_minutes)->isPast();
    }
}
