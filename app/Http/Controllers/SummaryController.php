<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Summary;
use App\Services\OpenAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
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

    /**
     * Send a new message to the LLM about a specific summary's context.
     */
    public function chat(Request $request, Summary $summary, OpenAiService $openAiService): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // 1. Save user message to database
        $summary->chatMessages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            // Mock offline response
            $mockResponse = "This is a simulated AI response about the summary content for your query: \"{$validated['message']}\".\n\nSince no OpenAI API Key was provided, here is how the model would analyze it:\n\n1. **Context**: Page range ".implode(', ', $summary->target_pages ?? []).".\n2. **Analysis**: We've analyzed the core concepts in this section to address your query.\n3. **Structured Summary Table**:\n\n| Item | Description | Status |\n|---|---|---|\n| Query | ".$validated['message']." | Addressed |\n| Memory | Retained from previous turns | Active |\n| Caching | Prefix matched successfully | Cached |";

            $summary->chatMessages()->create([
                'role' => 'assistant',
                'content' => $mockResponse,
                'tokens_used' => 150,
            ]);

            return redirect()->back();
        }

        // 2. Extract PDF segment if target pages are set
        $book = $summary->book;
        $targetPages = $summary->target_pages ?? [];
        $pdfPath = $book->getFirstMediaPath('file');
        $tempPdfPath = null;

        if (! empty($targetPages) && $pdfPath && file_exists($pdfPath)) {
            try {
                $pythonPath = '/Users/macbookair/miniconda3/bin/python3';
                if (! file_exists($pythonPath)) {
                    $pythonPath = 'python3';
                }
                $scriptPath = base_path('app/Services/pdf_page_extractor.py');

                $tempDir = storage_path('app/tmp');
                if (! file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                $tempPdfPath = $tempDir.'/'.uniqid('chat_').'.pdf';

                $result = Process::run([
                    $pythonPath,
                    $scriptPath,
                    $pdfPath,
                    $tempPdfPath,
                    implode(',', $targetPages),
                ]);

                if (! $result->successful() || ! file_exists($tempPdfPath)) {
                    Log::error('PDF page extractor script failed: '.$result->errorOutput());
                    $tempPdfPath = null;
                }
            } catch (\Exception $e) {
                Log::error('Error extracting PDF pages: '.$e->getMessage());
                $tempPdfPath = null;
            }
        }

        try {
            $pdfToSend = $tempPdfPath ?: $pdfPath;
            $pagesContext = ! empty($targetPages) ? 'Focus ONLY on the attached pages (extracted from pages '.implode(', ', $targetPages).' of the book).' : 'Analyze the attached PDF document.';

            // Build the initial prompt that was used to generate the summary
            $initialPrompt = "{$pagesContext}\n\nInitial summary request: {$summary->prompt_used}\n\nIMPORTANT: Please ensure the response is strictly valid Markdown syntax without enclosing it in triple backticks unless required for code snippets.";

            // Build the chat history:
            // Message 0 is the initial system summary request + PDF.
            // Message 1 is the assistant's initial generated summary.
            // Subsequent messages are from the database.
            $chatHistory = [];
            // Add the initial generated summary as the assistant's first turn response
            $chatHistory[] = [
                'role' => 'assistant',
                'content' => $summary->generated_summary,
            ];

            // Get existing chat messages, EXCLUDING the one we just saved at the very end
            $savedMessages = $summary->chatMessages()
                ->orderBy('created_at')
                ->get();

            foreach ($savedMessages as $msg) {
                $chatHistory[] = [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            }

            // Call LLM
            $reply = $openAiService->chatConversationWithPdf(
                $initialPrompt,
                $pdfToSend ? [$pdfToSend] : [],
                $chatHistory,
                null,
                config('services.openai.pdf_format', 'file')
            );

            // Save assistant reply
            $summary->chatMessages()->create([
                'role' => 'assistant',
                'content' => $reply,
                'tokens_used' => 1200, // estimated
            ]);

        } catch (\Exception $e) {
            Log::error('Error in Summary Chat LLM call: '.$e->getMessage());

            // Delete user message we just saved so they can retry
            $summary->chatMessages()->latest()->first()?->delete();

            if ($tempPdfPath && file_exists($tempPdfPath)) {
                unlink($tempPdfPath);
            }

            return redirect()->back()->withErrors(['chat' => 'AI Error: '.$e->getMessage()]);
        }

        if ($tempPdfPath && file_exists($tempPdfPath)) {
            unlink($tempPdfPath);
        }

        return redirect()->back();
    }

    /**
     * Clear conversation history for a specific summary.
     */
    public function clearChat(Summary $summary): RedirectResponse
    {
        $summary->chatMessages()->delete();

        return redirect()->back();
    }
}
