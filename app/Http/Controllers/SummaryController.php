<?php

namespace App\Http\Controllers;

use App\Enums\BookFileType;
use App\Models\Book;
use App\Models\Summary;
use App\Services\EpubExtractorService;
use App\Services\OpenAiService;
use App\Services\PdfImageExtractorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SummaryController extends Controller
{
    /**
     * Display a listing of all summaries, optionally filtered by book.
     */
    public function index(Request $request): Response
    {
        $bookId = $request->query('book_id');

        $query = Summary::whereHas('book', fn ($q) => $q->accessibleBy($request->user()))
            ->with(['book.media', 'bookSection']);

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
                'section_title' => $s->section_title,
                'target_pages' => $s->target_pages,
                'prompt_used' => $s->prompt_used,
                'generated_summary' => $s->generated_summary,
                'tokens_used' => $s->tokens_used,
                'created_at' => $s->created_at->diffForHumans(),
            ]);

        $books = Book::accessibleBy($request->user())
            ->orderBy('title')
            ->get(['id', 'title', 'author']);

        return Inertia::render('Summaries/Index', [
            'summaries' => $summaries,
            'books' => $books,
            'selectedBookId' => $bookId ? (int) $bookId : null,
        ]);
    }

    /**
     * Stream summary generation using Server-Sent Events (SSE).
     */
    public function stream(
        Summary $summary,
        OpenAiService $openAiService,
        EpubExtractorService $epubExtractorService,
        PdfImageExtractorService $pdfImageExtractorService
    ): StreamedResponse {
        $this->authorize('view', $summary->book);

        return response()->stream(function () use ($summary, $openAiService, $epubExtractorService, $pdfImageExtractorService) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            if (! empty($summary->generated_summary)) {
                echo 'data: '.json_encode(['content' => $summary->generated_summary])."\n\n";
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            $book = $summary->book;
            $targetPages = $summary->target_pages ?? [];
            $imagePaths = [];

            try {
                if ($book->file_type === BookFileType::Pdf) {
                    $pdfPath = $book->getFirstMediaPath('file');
                    if ($pdfPath && file_exists($pdfPath)) {
                        $imagePaths = $pdfImageExtractorService->extractPagesToImages($pdfPath, $targetPages);
                    }
                    $pagesContext = ! empty($targetPages) ? 'Focus ONLY on the attached page images (extracted from pages '.implode(', ', $targetPages).' of the book).' : 'Analyze the attached page images of the PDF document.';
                } else {
                    $epubPath = $book->getFirstMediaPath('file');
                    $tempPdfPath = null;
                    if ($summary->bookSection && $epubPath && file_exists($epubPath)) {
                        $tempPdfPath = $epubExtractorService->extractSectionToPdf($book, $summary->bookSection);
                        if ($tempPdfPath && file_exists($tempPdfPath)) {
                            $imagePaths = $pdfImageExtractorService->extractPagesToImages($tempPdfPath, []);
                            @unlink($tempPdfPath);
                        }
                    }
                    $pagesContext = ! empty($imagePaths) ? 'Focus ONLY on the attached page images (which represent section "'.($summary->bookSection?->title ?? '').'" of the book).' : 'Analyze the attached document.';
                }

                $fullPrompt = "{$pagesContext}\n\nUser request: {$summary->prompt_used}\n\nIMPORTANT: Please ensure the response is strictly valid Markdown syntax without enclosing it in triple backticks unless required for code snippets.";

                $accumulatedSummary = '';

                $openAiService->streamChatWithImages($fullPrompt, $imagePaths, function (string $chunk) use (&$accumulatedSummary) {
                    $accumulatedSummary .= $chunk;
                    echo 'data: '.json_encode(['content' => $chunk])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });

                if (! empty($accumulatedSummary)) {
                    $summary->update([
                        'generated_summary' => $accumulatedSummary,
                        'tokens_used' => 1200,
                    ]);
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (\Exception $e) {
                Log::error('Summary Stream Error: '.$e->getMessage());
                echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } finally {
                foreach ($imagePaths as $imgFile) {
                    if (file_exists($imgFile)) {
                        @unlink($imgFile);
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send a new message to the LLM about a specific summary's context.
     */
    public function chat(
        Request $request,
        Summary $summary,
        OpenAiService $openAiService,
        EpubExtractorService $epubExtractorService,
        PdfImageExtractorService $pdfImageExtractorService
    ): RedirectResponse {
        $this->authorize('view', $summary->book);

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
            $mockResponse = "This is a simulated AI response about the summary content for your query: \"{$validated['message']}\".\n\nSince no OpenAI API Key was provided, here is how the model would analyze it:\n\n1. **Context**: ".($summary->book?->file_type === BookFileType::Pdf ? 'Page range '.implode(', ', $summary->target_pages ?? []) : 'Section '.($summary->bookSection?->title ?? '')).".\n2. **Analysis**: We've analyzed the core concepts in this section to address your query.\n3. **Structured Summary Table**:\n\n| Item | Description | Status |\n|---|---|---|\n| Query | ".$validated['message']." | Addressed |\n| Memory | Retained from previous turns | Active |\n| Caching | Prefix matched successfully | Cached |";

            $summary->chatMessages()->create([
                'role' => 'assistant',
                'content' => $mockResponse,
                'tokens_used' => 150,
            ]);

            return redirect()->back();
        }

        $book = $summary->book;
        $targetPages = $summary->target_pages ?? [];
        $imagePaths = [];

        if ($book->file_type === BookFileType::Pdf) {
            $pdfPath = $book->getFirstMediaPath('file');
            if ($pdfPath && file_exists($pdfPath)) {
                try {
                    $imagePaths = $pdfImageExtractorService->extractPagesToImages($pdfPath, $targetPages);
                } catch (\Exception $e) {
                    Log::error('Error extracting PDF page images: '.$e->getMessage());
                }
            }
            $pagesContext = ! empty($targetPages) ? 'Focus ONLY on the attached page images (extracted from pages '.implode(', ', $targetPages).' of the book).' : 'Analyze the attached page images of the PDF document.';
        } else {
            $epubPath = $book->getFirstMediaPath('file');
            $tempPdfPath = null;
            if ($summary->bookSection && $epubPath && file_exists($epubPath)) {
                try {
                    $tempPdfPath = $epubExtractorService->extractSectionToPdf($book, $summary->bookSection);
                    if ($tempPdfPath && file_exists($tempPdfPath)) {
                        $imagePaths = $pdfImageExtractorService->extractPagesToImages($tempPdfPath, []);
                        unlink($tempPdfPath);
                        $tempPdfPath = null;
                    }
                } catch (\Exception $e) {
                    Log::error('Error extracting EPUB section to images: '.$e->getMessage());
                    if ($tempPdfPath && file_exists($tempPdfPath)) {
                        unlink($tempPdfPath);
                    }
                }
            }
            $pagesContext = ! empty($imagePaths) ? 'Focus ONLY on the attached page images (which represent section "'.($summary->bookSection?->title ?? '').'" of the book).' : 'Analyze the attached document.';
        }

        try {
            // Build the initial prompt that was used to generate the summary
            $initialPrompt = "{$pagesContext}\n\nInitial summary request: {$summary->prompt_used}\n\nIMPORTANT: Please ensure the response is strictly valid Markdown syntax without enclosing it in triple backticks unless required for code snippets.";

            // Build the chat history:
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

            // Call LLM using image payloads
            $reply = $openAiService->chatConversationWithImages(
                $initialPrompt,
                $imagePaths,
                $chatHistory
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

            foreach ($imagePaths as $imgFile) {
                if (file_exists($imgFile)) {
                    unlink($imgFile);
                }
            }

            return redirect()->back()->withErrors(['chat' => 'AI Error: '.$e->getMessage()]);
        }

        foreach ($imagePaths as $imgFile) {
            if (file_exists($imgFile)) {
                unlink($imgFile);
            }
        }

        return redirect()->back();
    }

    /**
     * Clear conversation history for a specific summary.
     */
    public function clearChat(Summary $summary): RedirectResponse
    {
        $this->authorize('view', $summary->book);

        $summary->chatMessages()->delete();

        return redirect()->back();
    }
}
