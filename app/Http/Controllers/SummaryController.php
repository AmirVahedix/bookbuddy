<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Summary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SummaryController extends Controller
{
    /**
     * Display a listing of all summaries, optionally filtered by book.
     */
    public function index(Request $request): Response
    {
        $bookId = $request->query('book_id');

        $query = Summary::with(['book.media', 'bookSection']);

        if ($bookId) {
            $query->where('book_id', $bookId);
        }

        $summaries = $query->orderByDesc('created_at')
            ->get()
            ->map(fn (Summary $s) => [
                'id' => $s->id,
                'book_id' => $s->book_id,
                'book_title' => $s->book?->title,
                'book_author' => $s->book?->author,
                'book_thumbnail_url' => $s->book?->getFirstMediaUrl('thumbnail'),
                'section_title' => $s->bookSection?->title,
                'target_pages' => $s->target_pages,
                'prompt_used' => $s->prompt_used,
                'generated_summary' => $s->generated_summary,
                'tokens_used' => $s->tokens_used,
                'created_at' => $s->created_at->diffForHumans(),
            ]);

        $books = Book::orderBy('title')->get(['id', 'title', 'author']);

        return Inertia::render('Summaries/Index', [
            'summaries' => $summaries,
            'books' => $books,
            'selectedBookId' => $bookId ? (int) $bookId : null,
        ]);
    }
}
