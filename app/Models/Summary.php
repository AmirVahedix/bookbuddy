<?php

namespace App\Models;

use Database\Factories\SummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['book_id', 'book_section_id', 'target_pages', 'prompt_used', 'generated_summary', 'tokens_used'])]
class Summary extends Model
{
    /** @use HasFactory<SummaryFactory> */
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
            'book_section_id' => 'integer',
            'target_pages' => 'array',
            'tokens_used' => 'integer',
        ];
    }

    /**
     * Get the book that owns the summary.
     *
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get the section that owns the summary.
     *
     * @return BelongsTo<BookSection, $this>
     */
    public function bookSection(): BelongsTo
    {
        return $this->belongsTo(BookSection::class);
    }

    /**
     * Get the chat messages for this summary.
     *
     * @return HasMany<SummaryChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(SummaryChatMessage::class)->orderBy('created_at');
    }
}
