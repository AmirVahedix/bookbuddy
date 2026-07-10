<?php

namespace App\Models;

use App\Enums\BookFileType;
use App\Enums\BookReadingStatus;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['title', 'author', 'file_type', 'reading_status', 'total_pages', 'current_page'])]
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
        'reading_status' => 'planned_for_future',
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
}
