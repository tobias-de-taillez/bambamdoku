<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Index-Zeile einer Hilfe-Meldung; Text, Zustand und Bildschirmfoto liegen im Ordner {@see $path}.
 *
 * @property int $id
 * @property string $reference Kennung, die der Meldende angezeigt bekommt
 * @property int|null $user_id
 * @property string $url
 * @property string|null $component
 * @property string $message
 * @property string $path Ordner relativ zur Platte „local“ (storage/app/private)
 * @property string $status offen|erledigt
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $user
 */
class HelpRequest extends Model
{
    public const OPEN = 'offen';

    public const DONE = 'erledigt';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
