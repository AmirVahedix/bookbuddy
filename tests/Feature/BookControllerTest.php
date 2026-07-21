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
            'reading_status' => BookReadingStatus::CurrentlyReading,
            'updated_at' => now()->subDay(),
        ]);
        $currentlyReading2 = Book::factory()->create([
            'reading_status' => BookReadingStatus::CurrentlyReading,
            'updated_at' => now(), // newer
        ]);
        $doneBook = Book::factory()->create([
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
        Book::factory()->count(3)->create();

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
            'reading_status' => BookReadingStatus::CurrentlyReading,
        ]);
        $doneBook = Book::factory()->create([
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

        $book1 = Book::factory()->create();
        $book1->tags()->attach($tag1->id);

        $book2 = Book::factory()->create();
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
        $book = Book::factory()->create();

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
        $book = Book::factory()->create();

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
        $book = Book::factory()->create();

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
        $book = Book::factory()->create();

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

    public function test_authenticated_user_can_summarize_epub_section(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $htmlFiles = [
            'text/chapter1.xhtml' => '<html><body><h1>Chapter 1</h1><p>Chapter 1 content</p></body></html>',
        ];
        $file = $this->createMockEpubFile('book.epub', $htmlFiles);

        $book = Book::create([
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
        $book = Book::factory()->create();
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
        $book = Book::factory()->create();

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
