<?php

namespace Tests\Feature;

use App\Enums\BookFileType;
use App\Models\Book;
use App\Models\BookSection;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Book can be created via factory and casts are correct.
     */
    public function test_book_can_be_created_via_factory(): void
    {
        $book = Book::factory()->create([
            'title' => 'Quantum Mechanics',
            'file_type' => BookFileType::Pdf,
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Quantum Mechanics',
            'file_type' => 'pdf',
        ]);

        $this->assertInstanceOf(BookFileType::class, $book->file_type);
        $this->assertEquals(BookFileType::Pdf, $book->file_type);

        // Verify model default attribute
        $newBook = new Book;
        $this->assertEquals(0, $newBook->current_page);

        // Verify database default when saving without specifying current_page
        $savedBook = Book::create([
            'title' => 'Default Test Book',
            'file_path' => 'books/default-test.pdf',
            'file_type' => BookFileType::Pdf,
        ]);
        $this->assertEquals(0, $savedBook->current_page);
    }

    /**
     * Test Book relationships.
     */
    public function test_book_relationships(): void
    {
        $book = Book::factory()->create();

        $section = BookSection::factory()->create([
            'book_id' => $book->id,
        ]);

        $summary = Summary::factory()->create([
            'book_id' => $book->id,
            'book_section_id' => $section->id,
            'target_pages' => [12, 13, 14],
        ]);

        $this->assertCount(1, $book->sections);
        $this->assertEquals($section->id, $book->sections->first()->id);

        $this->assertCount(1, $book->summaries);
        $this->assertEquals($summary->id, $book->summaries->first()->id);

        $this->assertEquals($book->id, $section->book->id);
        $this->assertCount(1, $section->summaries);
        $this->assertEquals($summary->id, $section->summaries->first()->id);

        $this->assertEquals($book->id, $summary->book->id);
        $this->assertEquals($section->id, $summary->bookSection->id);
        $this->assertEquals([12, 13, 14], $summary->target_pages);
    }
}
