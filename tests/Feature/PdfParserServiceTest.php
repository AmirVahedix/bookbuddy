<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\PdfParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PdfParserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_sections_are_successfully_parsed_and_stored(): void
    {
        // 1. Arrange
        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'sections' => [
                        [
                            'title' => 'Chapter 1: Introduction',
                            'page' => 1,
                            'level' => 1,
                            'end_page' => 9,
                        ],
                        [
                            'title' => '1.1 Background',
                            'page' => 2,
                            'level' => 2,
                            'end_page' => 9,
                        ],
                        [
                            'title' => 'Chapter 2: Setup',
                            'page' => 10,
                            'level' => 1,
                            'end_page' => 20,
                        ],
                    ],
                ]),
                exitCode: 0
            ),
        ]);

        $book = Book::factory()->create([
            'title' => 'Test Book',
            'file_type' => 'pdf',
        ]);

        $service = new PdfParserService;

        // 2. Act
        $service->parseAndStoreSections($book, '/fake/path/to/book.pdf');

        // 3. Assert
        $this->assertDatabaseCount('book_sections', 3);

        $this->assertDatabaseHas('book_sections', [
            'book_id' => $book->id,
            'title' => 'Chapter 1: Introduction',
            'section_identifier' => 'page-1',
            'level' => 1,
            'start_page' => 1,
            'end_page' => 9,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('book_sections', [
            'book_id' => $book->id,
            'title' => '1.1 Background',
            'section_identifier' => 'page-2',
            'level' => 2,
            'start_page' => 2,
            'end_page' => 9,
            'order' => 2,
        ]);

        $this->assertDatabaseHas('book_sections', [
            'book_id' => $book->id,
            'title' => 'Chapter 2: Setup',
            'section_identifier' => 'page-10',
            'level' => 1,
            'start_page' => 10,
            'end_page' => 20,
            'order' => 3,
        ]);
    }

    public function test_pdf_sections_parsing_handles_process_failure_gracefully(): void
    {
        // 1. Arrange
        Process::fake([
            '*' => Process::result(
                errorOutput: 'Failed to run script',
                exitCode: 1
            ),
        ]);

        $book = Book::factory()->create([
            'title' => 'Test Book',
            'file_type' => 'pdf',
        ]);

        $service = new PdfParserService;

        // 2. Act
        $service->parseAndStoreSections($book, '/fake/path/to/book.pdf');

        // 3. Assert
        $this->assertDatabaseCount('book_sections', 0);
    }

    public function test_pdf_sections_parsing_handles_json_error_returned_from_script_gracefully(): void
    {
        // 1. Arrange
        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'error' => 'Some parsing error occurred',
                ]),
                exitCode: 0
            ),
        ]);

        $book = Book::factory()->create([
            'title' => 'Test Book',
            'file_type' => 'pdf',
        ]);

        $service = new PdfParserService;

        // 2. Act
        $service->parseAndStoreSections($book, '/fake/path/to/book.pdf');

        // 3. Assert
        $this->assertDatabaseCount('book_sections', 0);
    }
}
