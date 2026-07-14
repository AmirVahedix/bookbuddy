<?php

namespace App\Models;

use Database\Factories\SummaryChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['summary_id', 'role', 'content', 'tokens_used'])]
class SummaryChatMessage extends Model
{
    /** @use HasFactory<SummaryChatMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary_id' => 'integer',
            'tokens_used' => 'integer',
        ];
    }

    /**
     * Get the summary that owns this chat message.
     *
     * @return BelongsTo<Summary, $this>
     */
    public function summary(): BelongsTo
    {
        return $this->belongsTo(Summary::class);
    }
}
