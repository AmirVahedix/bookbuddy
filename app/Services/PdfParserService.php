<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookSection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PdfParserService
{
    /**
     * Parse the PDF and extract its sections.
     *
     * @param  string  $filePath  Absolute path to the PDF file.
     */
    public function parseAndStoreSections(Book $book, string $filePath): void
    {
        try {
            $pythonPath = '/Users/macbookair/miniconda3/bin/python3';
            if (! file_exists($pythonPath)) {
                $pythonPath = 'python3';
            }

            $scriptPath = base_path('app/Services/pdf_outline_parser.py');

            $result = Process::run([$pythonPath, $scriptPath, $filePath]);

            if (! $result->successful()) {
                Log::error('PDF outline parser script failed: '.$result->errorOutput());

                return;
            }

            $data = json_decode($result->output(), true);

            if (isset($data['error'])) {
                Log::error('PDF outline parser returned error: '.$data['error']);

                return;
            }

            if (! isset($data['sections']) || empty($data['sections'])) {
                Log::info("No outline/table of contents found for PDF book: {$book->id}");

                return;
            }

            $order = 1;
            foreach ($data['sections'] as $section) {
                $sectionIdentifier = 'page-'.$section['page'];

                BookSection::updateOrCreate(
                    [
                        'book_id' => $book->id,
                        'title' => mb_substr($section['title'], 0, 250),
                        'section_identifier' => $sectionIdentifier,
                        'level' => $section['level'],
                        'start_page' => $section['page'],
                    ],
                    [
                        'end_page' => $section['end_page'],
                        'order' => $order++,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Error parsing and storing PDF sections: '.$e->getMessage());
        }
    }
}
