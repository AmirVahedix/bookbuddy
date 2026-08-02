<?php

namespace Tests\Feature;

use App\Enums\BookFileType;
use App\Enums\BookReadingStatus;
use App\Models\Book;
use App\Models\BookSection;
use App\Models\Summary;
use App\Models\Tag;
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
            'reading_status' => BookReadingStatus::CurrentlyReading,
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Quantum Mechanics',
            'file_type' => 'pdf',
            'reading_status' => 'currently_reading',
        ]);

        $this->assertInstanceOf(BookFileType::class, $book->file_type);
        $this->assertEquals(BookFileType::Pdf, $book->file_type);
        $this->assertInstanceOf(BookReadingStatus::class, $book->reading_status);
        $this->assertEquals(BookReadingStatus::CurrentlyReading, $book->reading_status);

        // Verify model default attribute
        $newBook = new Book;
        $this->assertEquals(0, $newBook->current_page);
        $this->assertEquals(BookReadingStatus::CurrentlyReading, $newBook->reading_status);

        // Verify database default when saving without specifying current_page or reading_status
        $savedBook = Book::create([
            'title' => 'Default Test Book',
            'file_type' => BookFileType::Pdf,
        ]);
        $this->assertEquals(0, $savedBook->current_page);
        $this->assertEquals(BookReadingStatus::CurrentlyReading, $savedBook->reading_status);
    }

    /**
     * Test Spatie Media Library integration.
     */
    public function test_book_can_attach_media_files_and_thumbnails(): void
    {
        $book = Book::factory()->create();

        // Attach to file collection
        $book->addMediaFromString('%PDF-1.4 mock content')
            ->usingFileName('sample.pdf')
            ->toMediaCollection('file');

        // Attach to thumbnail collection
        $book->addMediaFromString('mock image data')
            ->usingFileName('cover.jpg')
            ->toMediaCollection('thumbnail');

        $this->assertTrue($book->hasMedia('file'));
        $this->assertEquals('sample.pdf', $book->getFirstMedia('file')->file_name);

        $this->assertTrue($book->hasMedia('thumbnail'));
        $this->assertEquals('cover.jpg', $book->getFirstMedia('thumbnail')->file_name);

        // Verify singleFile constraint: uploading a new file replaces the old one
        $book->addMediaFromString('different pdf content')
            ->usingFileName('new-sample.pdf')
            ->toMediaCollection('file');

        $book->refresh();
        $this->assertCount(1, $book->getMedia('file'));
        $this->assertEquals('new-sample.pdf', $book->getFirstMedia('file')->file_name);
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

    /**
     * Test many-to-many relationship with tags.
     */
    public function test_book_can_have_many_tags(): void
    {
        $book = Book::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        $book->tags()->attach($tags->pluck('id'));

        $this->assertCount(3, $book->tags);
        $this->assertEquals($tags->first()->id, $book->tags->first()->id);
    }

    /**
     * Test reverse many-to-many relationship from tags to books.
     */
    public function test_tag_can_belong_to_many_books(): void
    {
        $tag = Tag::factory()->create();
        $books = Book::factory()->count(2)->create();

        $tag->books()->attach($books->pluck('id'));

        $this->assertCount(2, $tag->books);
        $this->assertEquals($books->first()->id, $tag->books->first()->id);
    }
}
