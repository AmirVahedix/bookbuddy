<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Summary;
use App\Models\SummaryChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthenticated users are redirected on chat actions.
     */
    public function test_unauthenticated_user_cannot_chat_or_clear(): void
    {
        $summary = Summary::factory()->create();

        $this->post("/summaries/{$summary->id}/chat", ['message' => 'Hello'])
            ->assertRedirect('/login');

        $this->delete("/summaries/{$summary->id}/chat")
            ->assertRedirect('/login');
    }

    /**
     * Test that an authenticated user can send a chat message and receive a simulated response.
     */
    public function test_authenticated_user_can_send_chat_message(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $summary = Summary::factory()->create([
            'book_id' => $book->id,
            'target_pages' => [1, 2, 3],
        ]);

        // Disable real LLM calls
        config(['services.openai.api_key' => '']);

        $response = $this->actingAs($user)
            ->post("/summaries/{$summary->id}/chat", [
                'message' => 'What is this section about?',
            ]);

        $response->assertRedirect();

        // Assert user message and mock assistant message are in the database
        $this->assertDatabaseHas('summary_chat_messages', [
            'summary_id' => $summary->id,
            'role' => 'user',
            'content' => 'What is this section about?',
        ]);

        $this->assertDatabaseHas('summary_chat_messages', [
            'summary_id' => $summary->id,
            'role' => 'assistant',
        ]);

        $this->assertEquals(2, $summary->chatMessages()->count());
    }

    /**
     * Test that conversation history is preloaded in the summary reader.
     */
    public function test_summary_reader_loads_chat_history(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $summary = Summary::factory()->create(['book_id' => $book->id]);

        $chatMessage = SummaryChatMessage::factory()->create([
            'summary_id' => $summary->id,
            'role' => 'user',
            'content' => 'Test Question',
        ]);

        $response = $this->actingAs($user)
            ->get("/books/{$book->id}/summaries/{$summary->id}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($chatMessage) {
            $page->component('Books/SummaryReader')
                ->has('summaries', 1)
                ->where('summaries.0.chat_messages.0.id', $chatMessage->id)
                ->where('summaries.0.chat_messages.0.role', 'user')
                ->where('summaries.0.chat_messages.0.content', 'Test Question');
        });
    }

    /**
     * Test that user can clear the chat history.
     */
    public function test_user_can_clear_chat_history(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $summary = Summary::factory()->create(['book_id' => $book->id]);

        SummaryChatMessage::factory()->count(3)->create([
            'summary_id' => $summary->id,
        ]);

        $this->assertEquals(3, $summary->chatMessages()->count());

        $response = $this->actingAs($user)
            ->delete("/summaries/{$summary->id}/chat");

        $response->assertRedirect();
        $this->assertEquals(0, $summary->chatMessages()->count());
    }
}
