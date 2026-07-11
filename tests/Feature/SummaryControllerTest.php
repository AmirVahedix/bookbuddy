<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthenticated users are redirected to login.
     */
    public function test_unauthenticated_user_cannot_access_summaries_list(): void
    {
        $this->get('/summaries')->assertRedirect('/login');
    }

    /**
     * Test that authenticated users can see the summaries list page.
     */
    public function test_authenticated_user_can_access_summaries_list(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => 'Design Patterns']);
        $summary = Summary::factory()->create([
            'book_id' => $book->id,
            'generated_summary' => 'This is a summary of design patterns.',
        ]);

        $response = $this->actingAs($user)->get('/summaries');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($book, $summary) {
            $page->component('Summaries/Index')
                ->has('summaries', 1)
                ->where('summaries.0.id', $summary->id)
                ->where('summaries.0.book_title', 'Design Patterns')
                ->where('summaries.0.book_id', $book->id)
                ->has('books', 1)
                ->where('books.0.id', $book->id)
                ->where('selectedBookId', null);
        });
    }

    /**
     * Test that summaries list can be filtered by book.
     */
    public function test_summaries_list_can_be_filtered_by_book(): void
    {
        $user = User::factory()->create();

        $book1 = Book::factory()->create(['title' => 'Book One']);
        $book2 = Book::factory()->create(['title' => 'Book Two']);

        $summary1 = Summary::factory()->create([
            'book_id' => $book1->id,
            'generated_summary' => 'Summary for Book One.',
        ]);

        $summary2 = Summary::factory()->create([
            'book_id' => $book2->id,
            'generated_summary' => 'Summary for Book Two.',
        ]);

        // Request with book_id filter for book1
        $response = $this->actingAs($user)->get('/summaries?book_id='.$book1->id);

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($book1, $summary1) {
            $page->component('Summaries/Index')
                ->has('summaries', 1)
                ->where('summaries.0.id', $summary1->id)
                ->where('summaries.0.book_title', 'Book One')
                ->where('selectedBookId', $book1->id);
        });
    }
}
