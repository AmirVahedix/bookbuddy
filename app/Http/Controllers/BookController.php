<?php

namespace App\Http\Controllers;

use App\Enums\BookReadingStatus;
use App\Models\Book;
use App\Models\Summary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    /**
     * Display the dashboard with currently reading books and stats.
     */
    public function dashboard(): Response
    {
        $currentlyReading = Book::where('reading_status', BookReadingStatus::CurrentlyReading)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Book $book) => $this->transformBook($book));

        $totalBooks = Book::count();
        $activeSummaries = Summary::count();
        $pagesRead = Book::sum('current_page');
        $totalPages = Book::sum('total_pages');

        $completionRate = $totalPages > 0
            ? (int) round(($pagesRead / $totalPages) * 100)
            : 0;

        return Inertia::render('Dashboard', [
            'currentlyReading' => $currentlyReading,
            'stats' => [
                'total_books' => $totalBooks,
                'active_summaries' => $activeSummaries,
                'pages_read' => $pagesRead,
                'completion_rate' => $completionRate,
            ],
        ]);
    }

    /**
     * Display a listing of all books, optionally filtered by status.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $query = Book::query();

        if ($status && BookReadingStatus::tryFrom($status)) {
            $query->where('reading_status', $status);
        }

        $books = $query->orderByDesc('updated_at')
            ->get()
            ->map(fn (Book $book) => $this->transformBook($book));

        return Inertia::render('Books/Index', [
            'books' => $books,
            'statusFilter' => $status,
        ]);
    }

    /**
     * Transform a Book model into a front-end friendly array.
     */
    protected function transformBook(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'file_type' => $book->file_type->value,
            'reading_status' => $book->reading_status->value,
            'total_pages' => $book->total_pages,
            'current_page' => $book->current_page,
            'thumbnail_url' => $book->getFirstMediaUrl('thumbnail'),
            'updated_at' => $book->updated_at->diffForHumans(),
        ];
    }
}
