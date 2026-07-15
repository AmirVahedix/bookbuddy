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
        config(['services.pdf.parser' => 'python']);
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
        config(['services.pdf.parser' => 'python']);
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
        config(['services.pdf.parser' => 'python']);
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

    public function test_pdf_sections_are_successfully_parsed_and_stored_via_php(): void
    {
        // 1. Arrange
        config(['services.pdf.parser' => 'php']);

        $pdfContent = <<<'PDF'
%PDF-1.4
1 0 obj
<<
  /Type /Catalog
  /Pages 2 0 R
  /Outlines 6 0 R
>>
endobj
2 0 obj
<<
  /Type /Pages
  /Kids [ 3 0 R 4 0 R 5 0 R ]
  /Count 3
>>
endobj
3 0 obj
<<
  /Type /Page
  /Parent 2 0 R
>>
endobj
4 0 obj
<<
  /Type /Page
  /Parent 2 0 R
>>
endobj
5 0 obj
<<
  /Type /Page
  /Parent 2 0 R
>>
endobj
6 0 obj
<<
  /Type /Outlines
  /First 7 0 R
  /Last 8 0 R
>>
endobj
7 0 obj
<<
  /Title (Chapter 1: Intro)
  /Parent 6 0 R
  /Next 8 0 R
  /First 9 0 R
  /Dest [ 3 0 R /XYZ null null null ]
>>
endobj
8 0 obj
<<
  /Title (Chapter 2: Detail)
  /Parent 6 0 R
  /Prev 7 0 R
  /Dest [ 5 0 R /XYZ null null null ]
>>
endobj
9 0 obj
<<
  /Title (Sub-section 1.1)
  /Parent 7 0 R
  /Dest [ 4 0 R /XYZ null null null ]
>>
endobj
xref
0 10
0000000000 65535 f
trailer
<<
  /Size 10
  /Root 1 0 R
>>
startxref
0
%%EOF
PDF;

        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_test_');
        file_put_contents($tempFile, $pdfContent);

        $book = Book::factory()->create([
            'title' => 'Test PHP Book',
            'file_type' => 'pdf',
        ]);

        $service = new PdfParserService;

        // 2. Act
        $service->parseAndStoreSections($book, $tempFile);

        // Cleanup
        @unlink($tempFile);

        // 3. Assert
        $this->assertDatabaseCount('book_sections', 3);

        $this->assertDatabaseHas('book_sections', [
            'book_id' => $book->id,
            'title' => 'Chapter 1: Intro',
            'section_identifier' => 'page-1',
            'level' => 1,
            'start_page' => 1,
            'end_page' => 2,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('book_sections', [
            'book_id' => $book->id,
            'title' => 'Sub-section 1.1',
            'section_identifier' => 'page-2',
            'level' => 2,
            'start_page' => 2,
            'end_page' => 2,
            'order' => 2,
        ]);

        $this->assertDatabaseHas('book_sections', [
            'book_id' => $book->id,
            'title' => 'Chapter 2: Detail',
            'section_identifier' => 'page-3',
            'level' => 1,
            'start_page' => 3,
            'end_page' => 3,
            'order' => 3,
        ]);
    }

    public function test_pdf_sections_parsing_via_php_handles_missing_file_gracefully(): void
    {
        // 1. Arrange
        config(['services.pdf.parser' => 'php']);

        $book = Book::factory()->create([
            'title' => 'Test PHP Book',
            'file_type' => 'pdf',
        ]);

        $service = new PdfParserService;

        // 2. Act
        $service->parseAndStoreSections($book, '/non/existent/path/book.pdf');

        // 3. Assert
        $this->assertDatabaseCount('book_sections', 0);
    }

    public function test_pdf_sections_parsing_via_php_handles_no_outlines_gracefully(): void
    {
        // 1. Arrange
        config(['services.pdf.parser' => 'php']);

        $pdfContent = <<<'PDF'
%PDF-1.4
1 0 obj
<<
  /Type /Catalog
  /Pages 2 0 R
>>
endobj
2 0 obj
<<
  /Type /Pages
  /Kids [ 3 0 R ]
  /Count 1
>>
endobj
3 0 obj
<<
  /Type /Page
  /Parent 2 0 R
>>
endobj
trailer
<<
  /Size 4
  /Root 1 0 R
>>
%%EOF
PDF;

        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_test_');
        file_put_contents($tempFile, $pdfContent);

        $book = Book::factory()->create([
            'title' => 'Test PHP Book',
            'file_type' => 'pdf',
        ]);

        $service = new PdfParserService;

        // 2. Act
        $service->parseAndStoreSections($book, $tempFile);

        // Cleanup
        @unlink($tempFile);

        // 3. Assert
        $this->assertDatabaseCount('book_sections', 0);
    }
}
