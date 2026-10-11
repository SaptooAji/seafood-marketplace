<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Domain\Account\Enums\SocialProvider;
use App\Models\User;
use Database\Factories\Domain\Account\Models\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An external sign-in identity (Google, Facebook) linked to a user.
 * One provider account belongs to exactly one user; a user may link several providers.
 */
#[Fillable(['user_id', 'provider', 'provider_id'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
        ];
    }

    /**
     * Get the user who owns this social account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
