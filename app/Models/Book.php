<?php

namespace App\Models;

use App\Enums\BookFileType;
use App\Enums\BookReadingStatus;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['user_id', 'title', 'author', 'file_type', 'reading_status', 'total_pages', 'current_page'])]
class Book extends Model implements HasMedia
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_page' => 0,
        'reading_status' => 'currently_reading',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => BookFileType::class,
            'reading_status' => BookReadingStatus::class,
            'total_pages' => 'integer',
            'current_page' => 'integer',
        ];
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')
            ->singleFile();

        $this->addMediaCollection('thumbnail')
            ->singleFile();
    }

    /**
     * Get the creator user of the book.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the users who have shared access to this book.
     *
     * @return BelongsToMany<User, $this>
     */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'book_user')->withTimestamps();
    }

    /**
     * Scope a query to only include books accessible by the given user.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('sharedUsers', fn (Builder $sq) => $sq->where('users.id', $user->id));
        });
    }

    /**
     * Check if a given user has access to this book (creator or shared).
     */
    public function hasAccess(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->sharedUsers()->where('users.id', $user->id)->exists();
    }

    /**
     * Check if a given user is the creator of this book.
     */
    public function isCreatedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Get the sections of the book.
     *
     * @return HasMany<BookSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(BookSection::class);
    }

    /**
     * Get the summaries of the book.
     *
     * @return HasMany<Summary, $this>
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    /**
     * Get the tags associated with the book.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
