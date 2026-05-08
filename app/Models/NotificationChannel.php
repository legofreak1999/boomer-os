<?php

namespace App\Models;

use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'label', 'config', 'enabled'])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory;

    public const TYPE_DISCORD = 'discord';

    public const TYPE_TELEGRAM = 'telegram';

    public const TYPE_EMAIL = 'email';

    public const TYPE_SIGNAL = 'signal';

    public const TYPE_NTFY = 'ntfy';

    public const TYPES = [
        self::TYPE_DISCORD,
        self::TYPE_TELEGRAM,
        self::TYPE_EMAIL,
        self::TYPE_SIGNAL,
        self::TYPE_NTFY,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
