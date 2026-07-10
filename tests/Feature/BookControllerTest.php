<?php

namespace Tests\Feature;

use App\Enums\BookReadingStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
