<?php

namespace App\Models;

use App\Enums\BookFileType;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'author', 'file_path', 'file_type', 'total_pages', 'current_page'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_page' => 0,
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
            'total_pages' => 'integer',
            'current_page' => 'integer',
        ];
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
