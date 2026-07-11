<?php

namespace Tests\Feature;

use App\Enums\BookReadingStatus;
use App\Models\Book;
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

    public function test_authenticated_user_can_store_book_with_valid_epub_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip');

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
}
