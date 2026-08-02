<?php

namespace Tests\Feature;

use App\Enums\BookReadingStatus;
use App\Models\Book;
use App\Models\BookSection;
use App\Models\Summary;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_dashboard_or_books(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/books')->assertRedirect('/login');
    }

    public function test_dashboard_displays_currently_reading_books_and_stats(): void
    {
        $user = User::factory()->create();

        // Create books with different status
        $currentlyReading1 = Book::factory()->create([
            'user_id' => $user->id,
            'reading_status' => BookReadingStatus::CurrentlyReading,
            'updated_at' => now()->subDay(),
        ]);
        $currentlyReading2 = Book::factory()->create([
            'user_id' => $user->id,
            'reading_status' => BookReadingStatus::CurrentlyReading,
            'updated_at' => now(), // newer
        ]);
        $doneBook = Book::factory()->create([
            'user_id' => $user->id,
            'reading_status' => BookReadingStatus::Done,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($currentlyReading1, $currentlyReading2) {
            $page->component('Dashboard')
                ->has('currentlyReading', 2)
                ->where('currentlyReading.0.id', $currentlyReading2->id) // Sorted desc by updated_at
                ->where('currentlyReading.1.id', $currentlyReading1->id)
                ->where('stats.total_books', 3)
                ->where('stats.active_summaries', 0);
        });
    }

    public function test_books_index_lists_all_books(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/books');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Books/Index')
                ->has('books', 3)
                ->where('statusFilter', null);
        });
    }

    public function test_books_index_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();

        Book::factory()->count(2)->create([
            'user_id' => $user->id,
            'reading_status' => BookReadingStatus::CurrentlyReading,
        ]);
        $doneBook = Book::factory()->create([
            'user_id' => $user->id,
            'reading_status' => BookReadingStatus::Done,
        ]);

        $response = $this->actingAs($user)->get('/books?status=done');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($doneBook) {
            $page->component('Books/Index')
                ->has('books', 1)
                ->where('books.0.id', $doneBook->id)
                ->where('statusFilter', 'done');
        });
    }

    public function test_unauthenticated_user_cannot_access_create_book_page(): void
    {
        $this->get('/books/create')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_create_book_page(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/books/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Books/Create'));
    }

    public function test_authenticated_user_can_store_book_with_valid_pdf_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('book.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post('/books', [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'file' => $file,
        ]);

        $response->assertRedirect('/books');

        $this->assertDatabaseHas('books', [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'file_type' => 'pdf',
            'reading_status' => 'currently_reading',
        ]);

        $book = Book::firstWhere('title', 'Test Book');
        $this->assertNotNull($book);
        $this->assertTrue($book->hasMedia('file'));
        $this->assertEquals('book.pdf', $book->getFirstMedia('file')->file_name);
    }

    public function test_authenticated_user_can_store_book_with_valid_pdf_file_and_thumbnail(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('book.pdf', 100, 'application/pdf');
        $thumbnail = UploadedFile::fake()->image('cover.jpg', 600, 800);

        $response = $this->actingAs($user)->post('/books', [
            'title' => 'Test Book with Cover',
            'author' => 'Test Author 3',
            'file' => $file,
            'thumbnail' => $thumbnail,
        ]);

        $response->assertRedirect('/books');

        $this->assertDatabaseHas('books', [
            'title' => 'Test Book with Cover',
            'author' => 'Test Author 3',
            'file_type' => 'pdf',
        ]);

        $book = Book::firstWhere('title', 'Test Book with Cover');
        $this->assertNotNull($book);
        $this->assertTrue($book->hasMedia('file'));
        $this->assertTrue($book->hasMedia('thumbnail'));
        $this->assertEquals('book.pdf', $book->getFirstMedia('file')->file_name);
        $this->assertEquals('cover.jpg', $book->getFirstMedia('thumbnail')->file_name);
    }

    public function test_authenticated_user_can_store_book_with_valid_epub_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $longTitle = str_repeat('A', 300);
        $expectedTruncatedTitle = str_repeat('A', 252).'...';

        $htmlFiles = [
            'text/intro.xhtml' => '<html><head><title>Introduction Page</title></head><body><p>This is page intro with no headings.</p></body></html>',
            'text/chapter1.xhtml' => '<html><head><title>Chapter 1</title></head><body>'.
                '<p>Some preamble text that shouldn\'t be orphan.</p>'.
                '<h1 id="c1-title">Chapter 1 Title</h1>'.
                '<p>Some content for section 1.</p>'.
                '<h2 id="c1-sub1">Section 1.1 Subtitle</h2>'.
                '<p>Some content for section 1.1.</p>'.
                '<h3 id="c1-subsub1">Subsection 1.1.1</h3>'.
                '<p>Sub-content</p>'.
                '<h4>Deep heading</h4>'.
                '<p>Deep content</p>'.
                '<h5 id="long-title">'.$longTitle.'</h5>'.
                '<p>Content under long heading</p>'.
                '</body></html>',
        ];

        $file = $this->createMockEpubFile('book.epub', $htmlFiles);

        $response = $this->actingAs($user)->post('/books', [
            'title' => 'Test Book 2',
            'author' => 'Test Author 2',
            'file' => $file,
        ]);

        $response->assertRedirect('/books');

        $this->assertDatabaseHas('books', [
            'title' => 'Test Book 2',
            'author' => 'Test Author 2',
            'file_type' => 'epub',
        ]);

        $book = Book::firstWhere('title', 'Test Book 2');
        $this->assertNotNull($book);
        $this->assertTrue($book->hasMedia('file'));
        $this->assertEquals('book.epub', $book->getFirstMedia('file')->file_name);

        $sections = $book->sections()->orderBy('order')->get();

        $this->assertCount(7, $sections);

        // Section 1: Intro page
        $this->assertEquals('Introduction Page', $sections[0]->title);
        $this->assertEquals('text/intro.xhtml', $sections[0]->section_identifier);
        $this->assertNull($sections[0]->level);
        $this->assertEquals(1, $sections[0]->order);

        // Section 2: Preamble
        $this->assertEquals('Chapter 1 (Introduction)', $sections[1]->title);
        $this->assertEquals('text/chapter1.xhtml', $sections[1]->section_identifier);
        $this->assertNull($sections[1]->level);
        $this->assertEquals(2, $sections[1]->order);

        // Section 3: h1
        $this->assertEquals('Chapter 1 Title', $sections[2]->title);
        $this->assertEquals('text/chapter1.xhtml#c1-title', $sections[2]->section_identifier);
        $this->assertEquals(1, $sections[2]->level);
        $this->assertEquals(3, $sections[2]->order);

        // Section 4: h2
        $this->assertEquals('Section 1.1 Subtitle', $sections[3]->title);
        $this->assertEquals('text/chapter1.xhtml#c1-sub1', $sections[3]->section_identifier);
        $this->assertEquals(2, $sections[3]->level);
        $this->assertEquals(4, $sections[3]->order);

        // Section 5: h3
        $this->assertEquals('Subsection 1.1.1', $sections[4]->title);
        $this->assertEquals('text/chapter1.xhtml#c1-subsub1', $sections[4]->section_identifier);
        $this->assertEquals(3, $sections[4]->level);
        $this->assertEquals(5, $sections[4]->order);

        // Section 6: h4
        $this->assertEquals('Deep heading', $sections[5]->title);
        $this->assertEquals('text/chapter1.xhtml#h4-3', $sections[5]->section_identifier);
        $this->assertEquals(4, $sections[5]->level);
        $this->assertEquals(6, $sections[5]->order);

        // Section 7: h5 (very long heading)
        $this->assertEquals($expectedTruncatedTitle, $sections[6]->title);
        $this->assertEquals('text/chapter1.xhtml#long-title', $sections[6]->section_identifier);
        $this->assertEquals(5, $sections[6]->level);
        $this->assertEquals(7, $sections[6]->order);
    }

    public function test_store_book_requires_title_and_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/books', [
            'title' => '',
            'file' => null,
        ]);

        $response->assertSessionHasErrors(['title', 'file']);
    }

    public function test_store_book_validates_file_extension(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invalid.txt', 50, 'text/plain');

        $response = $this->actingAs($user)->post('/books', [
            'title' => 'Invalid Book',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors(['file']);
    }

    public function test_books_index_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();

        $tag1 = Tag::factory()->create(['name' => 'Fiction']);
        $tag2 = Tag::factory()->create(['name' => 'Science']);

        $book1 = Book::factory()->create(['user_id' => $user->id]);
        $book1->tags()->attach($tag1->id);

        $book2 = Book::factory()->create(['user_id' => $user->id]);
        $book2->tags()->attach($tag2->id);

        $response = $this->actingAs($user)->get('/books?tag=Fiction');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($book1) {
            $page->component('Books/Index')
                ->has('books', 1)
                ->where('books.0.id', $book1->id)
                ->where('tagFilter', 'Fiction');
        });
    }

    public function test_create_book_page_passes_existing_tags_list(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'Design']);
        Tag::factory()->create(['name' => 'Architecture']);

        $response = $this->actingAs($user)->get('/books/create');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Books/Create')
                ->has('tags', 2)
                ->where('tags.0.name', 'Architecture')
                ->where('tags.1.name', 'Design');
        });
    }

    public function test_store_book_associates_existing_and_new_tags(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $existingTag = Tag::factory()->create(['name' => 'ExistingTag']);
        $file = UploadedFile::fake()->create('book.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post('/books', [
            'title' => 'Tagged Book',
            'author' => 'Tagged Author',
            'file' => $file,
            'tags' => ['ExistingTag', 'BrandNewTag'],
        ]);

        $response->assertRedirect('/books');

        $this->assertDatabaseHas('books', [
            'title' => 'Tagged Book',
        ]);

        $book = Book::firstWhere('title', 'Tagged Book');
        $this->assertNotNull($book);

        $this->assertCount(2, $book->tags);
        $this->assertTrue($book->tags->contains('name', 'ExistingTag'));
        $this->assertTrue($book->tags->contains('name', 'BrandNewTag'));
        $this->assertDatabaseHas('tags', ['name' => 'BrandNewTag']);
    }

    public function test_authenticated_user_can_access_book_show_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($book) {
            $page->component('Books/Show')
                ->where('book.id', $book->id)
                ->has('sections')
                ->has('summaries');
        });
    }

    public function test_authenticated_user_can_access_standalone_reader_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/books/{$book->id}/read");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($book) {
            $page->component('Books/Read')
                ->where('book.id', $book->id);
        });
    }

    public function test_authenticated_user_can_update_reading_progress(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'total_pages' => 200,
            'current_page' => 10,
            'reading_status' => BookReadingStatus::CurrentlyReading,
        ]);

        $response = $this->actingAs($user)->patch("/books/{$book->id}/progress", [
            'current_page' => 50,
            'reading_status' => 'currently_reading',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'current_page' => 50,
            'reading_status' => 'currently_reading',
        ]);
    }

    public function test_authenticated_user_can_trigger_summarization_with_api_fallback(): void
    {
        config(['services.openai.api_key' => '']);
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/books/{$book->id}/summarize", [
            'start_page' => 1,
            'end_page' => 5,
            'prompt' => 'Key themes',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('summaries', [
            'book_id' => $book->id,
            'prompt_used' => 'Key themes',
        ]);
    }

    public function test_summarization_correctly_associates_with_overlapping_book_sections(): void
    {
        config(['services.openai.api_key' => '']);
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // Create some sections
        $section1 = BookSection::factory()->create([
            'book_id' => $book->id,
            'start_page' => 1,
            'end_page' => 10,
        ]);
        $section2 = BookSection::factory()->create([
            'book_id' => $book->id,
            'start_page' => 11,
            'end_page' => 20,
        ]);

        // Summarize page 5 to 12. Since start_page is 5, it falls inside section1.
        $response = $this->actingAs($user)->post("/books/{$book->id}/summarize", [
            'start_page' => 5,
            'end_page' => 12,
            'prompt' => 'Analyze core concepts',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('summaries', [
            'book_id' => $book->id,
            'book_section_id' => $section1->id,
            'prompt_used' => 'Analyze core concepts',
        ]);

        // Summarize page 15 to 18. Falls inside section2.
        $response = $this->actingAs($user)->post("/books/{$book->id}/summarize", [
            'start_page' => 15,
            'end_page' => 18,
            'prompt' => 'Key arguments',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('summaries', [
            'book_id' => $book->id,
            'book_section_id' => $section2->id,
            'prompt_used' => 'Key arguments',
        ]);
    }

    public function test_summarization_associates_with_exact_child_section_over_parent_section(): void
    {
        config(['services.openai.api_key' => '']);
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'file_type' => 'pdf']);

        // Parent Chapter covering pages 1 to 50 (Level 1)
        $parentSection = BookSection::factory()->create([
            'book_id' => $book->id,
            'title' => 'Chapter 1: Foundations',
            'level' => 1,
            'start_page' => 1,
            'end_page' => 50,
            'order' => 1,
        ]);

        // Child Subsection covering pages 1 to 10 (Level 2)
        $childSection = BookSection::factory()->create([
            'book_id' => $book->id,
            'title' => 'Section 1.1: Introduction',
            'level' => 2,
            'start_page' => 1,
            'end_page' => 10,
            'order' => 2,
        ]);

        // 1. Summarize by page range (1-10) without explicitly sending book_section_id
        $response = $this->actingAs($user)->post("/books/{$book->id}/summarize", [
            'start_page' => 1,
            'end_page' => 10,
            'prompt' => 'Summarize section 1.1',
        ]);

        $response->assertRedirect();
        $summary = Summary::latest('id')->first();
        $this->assertEquals($childSection->id, $summary->book_section_id);
        $this->assertEquals('Section 1.1: Introduction', $summary->section_title);
        $this->assertStringContainsString('# Summary for Section 1.1: Introduction', $summary->generated_summary);

        // 2. Summarize with explicit book_section_id
        $response2 = $this->actingAs($user)->post("/books/{$book->id}/summarize", [
            'book_section_id' => $childSection->id,
            'start_page' => 1,
            'end_page' => 10,
            'prompt' => 'Explicit section summarize',
        ]);

        $response2->assertRedirect();
        $summary2 = Summary::latest('id')->first();
        $this->assertEquals($childSection->id, $summary2->book_section_id);
        $this->assertEquals('Section 1.1: Introduction', $summary2->section_title);
        $this->assertStringContainsString('# Summary for Section 1.1: Introduction', $summary2->generated_summary);
    }

    public function test_authenticated_user_can_summarize_epub_section(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $htmlFiles = [
            'text/chapter1.xhtml' => '<html><body><h1>Chapter 1</h1><p>Chapter 1 content</p></body></html>',
        ];
        $file = $this->createMockEpubFile('book.epub', $htmlFiles);

        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'Test EPUB Book',
            'author' => 'Test Author',
            'file_type' => 'epub',
        ]);
        $book->addMedia($file)->toMediaCollection('file');

        $section = BookSection::create([
            'book_id' => $book->id,
            'title' => 'Chapter 1',
            'section_identifier' => 'text/chapter1.xhtml',
            'order' => 1,
        ]);

        config(['services.openai.api_key' => '']);

        $response = $this->actingAs($user)->post("/books/{$book->id}/summarize", [
            'book_section_id' => $section->id,
            'prompt' => 'Provide a core concepts summary',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('summaries', [
            'book_id' => $book->id,
            'book_section_id' => $section->id,
            'prompt_used' => 'Provide a core concepts summary',
        ]);

        $summary = Summary::where('book_section_id', $section->id)->first();
        $this->assertNotNull($summary);
        $this->assertStringContainsString('simulated summary', $summary->generated_summary);
    }

    public function test_authenticated_user_can_access_summaries_reader_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $summary = Summary::create([
            'book_id' => $book->id,
            'prompt_used' => 'Test prompt',
            'generated_summary' => 'Test summary content',
            'target_pages' => [1, 2, 3],
        ]);

        $response = $this->actingAs($user)->get("/books/{$book->id}/summaries");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($summary) {
            $page->component('Books/SummaryReader')
                ->has('book')
                ->has('summaries', 1)
                ->where('summaries.0.id', $summary->id)
                ->where('initialSummaryId', null);
        });

        // Test with summary id parameter
        $responseWithId = $this->actingAs($user)->get("/books/{$book->id}/summaries/{$summary->id}");
        $responseWithId->assertStatus(200);
        $responseWithId->assertInertia(function ($page) use ($summary) {
            $page->component('Books/SummaryReader')
                ->where('initialSummaryId', $summary->id);
        });
    }

    public function test_unauthenticated_user_cannot_delete_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->delete("/books/{$book->id}");

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_authenticated_user_can_delete_book(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // Add some media file to ensure it's deleted
        $file = UploadedFile::fake()->create('book.pdf', 100, 'application/pdf');
        $book->addMedia($file)->toMediaCollection('file');

        // Create related models to verify cascade deletion
        $section = BookSection::factory()->create(['book_id' => $book->id]);
        $summary = Summary::create([
            'book_id' => $book->id,
            'book_section_id' => $section->id,
            'prompt_used' => 'Test prompt',
            'generated_summary' => 'Test summary content',
        ]);

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('book_sections', ['id' => $section->id]);
        $this->assertDatabaseHas('summaries', ['id' => $summary->id]);
        $this->assertTrue($book->hasMedia('file'));

        $response = $this->actingAs($user)->delete("/books/{$book->id}");

        $response->assertRedirect('/books');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('summaries', ['id' => $summary->id]);
        $this->assertCount(0, $book->media()->get());
    }

    public function test_user_can_toggle_section_read_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $section = BookSection::factory()->create([
            'book_id' => $book->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch("/books/{$book->id}/sections/{$section->id}/toggle-read");

        $response->assertRedirect();
        $this->assertTrue($section->fresh()->is_read);

        $response2 = $this->actingAs($user)
            ->patch("/books/{$book->id}/sections/{$section->id}/toggle-read");

        $response2->assertRedirect();
        $this->assertFalse($section->fresh()->is_read);
    }

    public function test_book_show_includes_is_read_in_sections_prop(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $section = BookSection::factory()->create([
            'book_id' => $book->id,
            'is_read' => true,
        ]);

        $response = $this->actingAs($user)->get("/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($section) {
            $page->component('Books/Show')
                ->has('sections', 1)
                ->where('sections.0.id', $section->id)
                ->where('sections.0.is_read', true);
        });
    }

    public function test_marking_parent_section_as_read_marks_all_its_children_as_read(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $parent = BookSection::factory()->create([
            'book_id' => $book->id,
            'order' => 1,
            'level' => 1,
            'is_read' => false,
        ]);

        $child1 = BookSection::factory()->create([
            'book_id' => $book->id,
            'order' => 2,
            'level' => 2,
            'is_read' => false,
        ]);

        $child2 = BookSection::factory()->create([
            'book_id' => $book->id,
            'order' => 3,
            'level' => 3,
            'is_read' => false,
        ]);

        $nextChapter = BookSection::factory()->create([
            'book_id' => $book->id,
            'order' => 4,
            'level' => 1,
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch("/books/{$book->id}/sections/{$parent->id}/toggle-read");

        $response->assertRedirect();
        $this->assertTrue($parent->fresh()->is_read);
        $this->assertTrue($child1->fresh()->is_read);
        $this->assertTrue($child2->fresh()->is_read);
        $this->assertFalse($nextChapter->fresh()->is_read);
    }

    public function test_toggling_section_read_updates_book_total_progress_and_reading_status_and_handles_undo(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'total_pages' => 100,
            'current_page' => 0,
            'reading_status' => BookReadingStatus::PlannedForFuture,
        ]);

        $s1 = BookSection::factory()->create(['book_id' => $book->id, 'order' => 1, 'is_read' => false]);
        $s2 = BookSection::factory()->create(['book_id' => $book->id, 'order' => 2, 'is_read' => false]);
        $s3 = BookSection::factory()->create(['book_id' => $book->id, 'order' => 3, 'is_read' => false]);
        $s4 = BookSection::factory()->create(['book_id' => $book->id, 'order' => 4, 'is_read' => false]);

        // Mark section 2 as read (highest read section index = 2 out of 4 -> 50%)
        $this->actingAs($user)->patch("/books/{$book->id}/sections/{$s2->id}/toggle-read");

        $book->refresh();
        $this->assertEquals(50, $book->current_page);
        $this->assertEquals(BookReadingStatus::CurrentlyReading, $book->reading_status);

        // Mark section 4 as read (highest read section index = 4 out of 4 -> 100%)
        $this->actingAs($user)->patch("/books/{$book->id}/sections/{$s4->id}/toggle-read");

        $book->refresh();
        $this->assertEquals(100, $book->current_page);
        $this->assertEquals(BookReadingStatus::Done, $book->reading_status);

        // Undo marking section 4 as read (un-check s4 -> highest read section index becomes 2 out of 4 -> 50%)
        $this->actingAs($user)->patch("/books/{$book->id}/sections/{$s4->id}/toggle-read");

        $book->refresh();
        $this->assertEquals(50, $book->current_page);
        $this->assertEquals(BookReadingStatus::CurrentlyReading, $book->reading_status);

        // Undo marking section 2 as read (un-check s2 -> 0 sections read -> 0%)
        $this->actingAs($user)->patch("/books/{$book->id}/sections/{$s2->id}/toggle-read");

        $book->refresh();
        $this->assertEquals(0, $book->current_page);
        $this->assertEquals(BookReadingStatus::PlannedForFuture, $book->reading_status);
    }

    public function test_user_can_delete_book_section(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $section = BookSection::factory()->create([
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)
            ->delete("/books/{$book->id}/sections/{$section->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('book_sections', ['id' => $section->id]);
    }

    public function test_unauthenticated_user_cannot_access_edit_book_page_or_update_book(): void
    {
        $book = Book::factory()->create();

        $this->get("/books/{$book->id}/edit")->assertRedirect('/login');
        $this->put("/books/{$book->id}", ['title' => 'Updated Title'])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_edit_book_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $tag = Tag::factory()->create(['name' => 'Fiction']);
        $book->tags()->attach($tag);

        $response = $this->actingAs($user)->get("/books/{$book->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($book) {
            $page->component('Books/Edit')
                ->where('book.id', $book->id)
                ->has('book.tags', 1)
                ->has('tags');
        });
    }

    public function test_authenticated_user_can_update_book_title_author_tags_and_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'author' => 'Original Author',
        ]);

        $newThumbnail = UploadedFile::fake()->image('new_cover.jpg', 600, 800);

        $response = $this->actingAs($user)->put("/books/{$book->id}", [
            'title' => 'Updated Title',
            'author' => 'Updated Author',
            'thumbnail' => $newThumbnail,
            'tags' => ['UpdatedTag1', 'UpdatedTag2'],
        ]);

        $response->assertRedirect("/books/{$book->id}");

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'author' => 'Updated Author',
        ]);

        $book->refresh();
        $this->assertTrue($book->hasMedia('thumbnail'));
        $this->assertEquals('new_cover.jpg', $book->getFirstMedia('thumbnail')->file_name);

        $this->assertCount(2, $book->tags);
        $this->assertTrue($book->tags->contains('name', 'UpdatedTag1'));
        $this->assertTrue($book->tags->contains('name', 'UpdatedTag2'));
    }

    public function test_authenticated_user_can_remove_book_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $thumbnail = UploadedFile::fake()->image('cover.jpg', 600, 800);
        $book->addMedia($thumbnail)->toMediaCollection('thumbnail');
        $this->assertTrue($book->hasMedia('thumbnail'));

        $response = $this->actingAs($user)->put("/books/{$book->id}", [
            'title' => $book->title,
            'author' => $book->author,
            'remove_thumbnail' => true,
        ]);

        $response->assertRedirect("/books/{$book->id}");
        $book->refresh();
        $this->assertFalse($book->hasMedia('thumbnail'));
    }

    protected function createMockEpubFile(string $filename, array $htmlFilesContent): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new \ZipArchive;
        $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('mimetype', 'application/epub+zip');

        $containerXml = '<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>';
        $zip->addFromString('META-INF/container.xml', $containerXml);

        $manifestItems = '';
        $spineItems = '';
        foreach (array_keys($htmlFilesContent) as $index => $href) {
            $id = 'item_'.$index;
            $manifestItems .= "<item id=\"{$id}\" href=\"{$href}\" media-type=\"application/xhtml+xml\"/>\n";
            $spineItems .= "<itemref idref=\"{$id}\"/>\n";
        }

        $opfContent = '<?xml version="1.0" encoding="utf-8"?>
<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="bookid" version="2.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>Mock Book</dc:title>
  </metadata>
  <manifest>
    '.$manifestItems.'
  </manifest>
  <spine>
    '.$spineItems.'
  </spine>
</package>';
        $zip->addFromString('OEBPS/content.opf', $opfContent);

        foreach ($htmlFilesContent as $href => $content) {
            $zip->addFromString('OEBPS/'.$href, $content);
        }

        $zip->close();

        return new UploadedFile(
            $tempFile,
            $filename,
            'application/epub+zip',
            null,
            true
        );
    }
}
