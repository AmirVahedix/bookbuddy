<?php

namespace App\Http\Controllers;

use App\Enums\BookFileType;
use App\Enums\BookReadingStatus;
use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Models\Summary;
use App\Models\Tag;
use App\Services\EpubParserService;
use App\Services\OpenAiService;
use App\Services\PdfParserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function __construct(
        protected EpubParserService $epubParserService,
        protected PdfParserService $pdfParserService,
        protected OpenAiService $openAiService
    ) {}

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
            'file_url' => $book->getFirstMediaUrl('file'),
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

        if ($fileType === BookFileType::Epub) {
            $this->epubParserService->parseAndStoreSections($book, $book->getFirstMediaPath('file'));
        } elseif ($fileType === BookFileType::Pdf) {
            $this->pdfParserService->parseAndStoreSections($book, $book->getFirstMediaPath('file'));
        }

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

    /**
     * Display the specified book.
     */
    public function show(Book $book): Response
    {
        $book->load('tags');

        $transformedBook = $this->transformBook($book);

        $sections = $book->sections()
            ->orderBy('order')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'section_identifier' => $s->section_identifier,
                'start_page' => $s->start_page,
                'end_page' => $s->end_page,
                'level' => $s->level,
                'order' => $s->order,
            ]);

        $summaries = $book->summaries()
            ->with('bookSection')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'book_section_id' => $s->book_section_id,
                'section_title' => $s->bookSection?->title,
                'target_pages' => $s->target_pages,
                'prompt_used' => $s->prompt_used,
                'generated_summary' => $s->generated_summary,
                'tokens_used' => $s->tokens_used,
                'created_at' => $s->created_at->diffForHumans(),
            ]);

        return Inertia::render('Books/Show', [
            'book' => $transformedBook,
            'sections' => $sections,
            'summaries' => $summaries,
        ]);
    }

    /**
     * Display the standalone reader.
     */
    public function read(Book $book): Response
    {
        $book->load('tags');

        $transformedBook = $this->transformBook($book);

        $sections = $book->sections()
            ->orderBy('order')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'section_identifier' => $s->section_identifier,
                'start_page' => $s->start_page,
                'end_page' => $s->end_page,
                'level' => $s->level,
                'order' => $s->order,
            ]);

        $summaries = $book->summaries()
            ->with('bookSection')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'book_section_id' => $s->book_section_id,
                'section_title' => $s->bookSection?->title,
                'target_pages' => $s->target_pages,
                'prompt_used' => $s->prompt_used,
                'generated_summary' => $s->generated_summary,
                'tokens_used' => $s->tokens_used,
                'created_at' => $s->created_at->diffForHumans(),
            ]);

        return Inertia::render('Books/Read', [
            'book' => $transformedBook,
            'sections' => $sections,
            'summaries' => $summaries,
        ]);
    }

    /**
     * Update the reading progress.
     */
    public function updateProgress(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:0', 'max:'.($book->total_pages ?? 999999)],
            'reading_status' => ['nullable', 'string'],
        ]);

        $updateData = [
            'current_page' => $validated['current_page'],
        ];

        if (isset($validated['reading_status'])) {
            $status = BookReadingStatus::tryFrom($validated['reading_status']);
            if ($status) {
                $updateData['reading_status'] = $status;
            }
        }

        // If they read to the end, mark status as done
        if ($book->total_pages > 0 && $validated['current_page'] >= $book->total_pages) {
            $updateData['reading_status'] = BookReadingStatus::Done;
        } elseif ($validated['current_page'] > 0 && $book->reading_status === BookReadingStatus::PlannedForFuture) {
            // If they start reading, update status to currently reading
            $updateData['reading_status'] = BookReadingStatus::CurrentlyReading;
        }

        $book->update($updateData);

        return redirect()->back();
    }

    /**
     * Generate an LLM summary for a page range.
     */
    public function summarize(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'start_page' => ['nullable', 'integer', 'min:1'],
            'end_page' => ['nullable', 'integer', 'min:1'],
            'pages' => ['nullable', 'array'],
            'pages.*' => ['integer'],
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        $targetPages = [];
        if (! empty($validated['pages'])) {
            $targetPages = $validated['pages'];
        } elseif (! empty($validated['start_page']) && ! empty($validated['end_page'])) {
            $targetPages = range($validated['start_page'], $validated['end_page']);
        }

        sort($targetPages);

        $apiKey = config('services.openai.api_key');
        $generatedSummary = '';
        $tokensUsed = null;

        if (empty($apiKey)) {
            $pagesStr = implode(', ', $targetPages);
            $generatedSummary = '# Summary for Pages '.($validated['start_page'] && $validated['end_page'] ? "{$validated['start_page']}-{$validated['end_page']}" : $pagesStr)."\n\n".
                "*(Note: OpenAI API Key was not set in configuration, this is a simulated summary based on your prompt)*\n\n".
                '## Key Points for prompt: "'.htmlspecialchars($validated['prompt'])."\"\n".
                "- **Topic Focus**: Summarizing content related to the specified pages.\n".
                "- **Key Insight**: The document details core concepts in this section, emphasizing architectural design principles, fault tolerance, and scalable data structure patterns.\n".
                "- **Summary Detail**: Under the requested query, this page range covers structural models, flow parameters, and operational semantics. Applications in this range focus on efficiency and modularity.\n";
            $tokensUsed = 250;
        } else {
            try {
                $pdfPath = $book->getFirstMediaPath('file');
                $pagesContext = ! empty($targetPages) ? 'Focus ONLY on pages '.implode(', ', $targetPages).' of the attached PDF document.' : 'Analyze the attached PDF document.';
                $fullPrompt = "{$pagesContext}\n\nUser request: {$validated['prompt']}";

                $generatedSummary = $this->openAiService->chatWithPdfs($fullPrompt, [$pdfPath]);
                $tokensUsed = 1200;
            } catch (\Exception $e) {
                return redirect()->back()->withErrors(['openai' => 'OpenAI Error: '.$e->getMessage()]);
            }
        }

        Summary::create([
            'book_id' => $book->id,
            'book_section_id' => null,
            'target_pages' => $targetPages,
            'prompt_used' => $validated['prompt'],
            'generated_summary' => $generatedSummary,
            'tokens_used' => $tokensUsed,
        ]);

        return redirect()->back();
    }
}
