<?php

namespace App\Http\Controllers;

use App\Enums\BookFileType;
use App\Enums\BookReadingStatus;
use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Models\Summary;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
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
        $currentlyReading = Book::with('tags')->where('reading_status', BookReadingStatus::CurrentlyReading)
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
     * Display a listing of all books, optionally filtered by status or tag.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $tag = $request->query('tag');

        $query = Book::with('tags');

        if ($status && BookReadingStatus::tryFrom($status)) {
            $query->where('reading_status', $status);
        }

        if ($tag) {
            $query->whereHas('tags', fn ($q) => $q->where('name', $tag));
        }

        $books = $query->orderByDesc('updated_at')
            ->get()
            ->map(fn (Book $book) => $this->transformBook($book));

        $allTags = Tag::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Books/Index', [
            'books' => $books,
            'statusFilter' => $status,
            'tagFilter' => $tag,
            'tags' => $allTags,
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
            'tags' => $book->tags->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->toArray(),
        ];
    }

    /**
     * Show the form for creating a new book.
     */
    public function create(): Response
    {
        $tags = Tag::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Books/Create', [
            'tags' => $tags,
        ]);
    }

    /**
     * Store a newly created book and its file.
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $fileType = BookFileType::tryFrom($extension);

        $book = Book::create([
            'title' => $request->validated('title'),
            'author' => $request->validated('author'),
            'file_type' => $fileType,
        ]);

        $book->addMedia($file)->toMediaCollection('file');

        $tagNames = $request->input('tags', []);
        if (! empty($tagNames)) {
            $tagIds = collect($tagNames)
                ->map(fn (string $name) => trim($name))
                ->filter()
                ->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id);
            $book->tags()->sync($tagIds);
        }

        return redirect()->route('books.index');
    }
}
