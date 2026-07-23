<?php

namespace App\Models;

use Database\Factories\BookSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['book_id', 'title', 'section_identifier', 'level', 'start_page', 'end_page', 'order', 'is_read'])]
class BookSection extends Model
{
    /** @use HasFactory<BookSectionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'book_id' => 'integer',
            'level' => 'integer',
            'start_page' => 'integer',
            'end_page' => 'integer',
            'order' => 'integer',
            'is_read' => 'boolean',
        ];
    }

    /**
     * Get the book that owns the section.
     *
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get the summaries for this section.
     *
     * @return HasMany<Summary, $this>
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }
}
