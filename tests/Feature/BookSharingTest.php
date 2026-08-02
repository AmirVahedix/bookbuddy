<?php

namespace Tests\Feature;

use App\Enums\BookFileType;
use App\Models\Book;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_book_associates_creator_user(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($book->isCreatedBy($user));
        $this->assertTrue($book->hasAccess($user));
        $this->assertEquals($user->id, $book->user->id);
    }

    public function test_owner_can_share_book_with_another_user_by_email(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create(['email' => 'friend@example.com']);
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post(route('books.share', $book), [
                'email' => 'friend@example.com',
            ]);

        $response->assertRedirect();
        $this->assertTrue($book->fresh()->sharedUsers->contains($otherUser));
        $this->assertTrue($book->fresh()->hasAccess($otherUser));
    }

    public function test_sharing_book_with_non_existent_email_returns_error(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post(route('books.share', $book), [
                'email' => 'nonexistent@example.com',
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_sharing_book_with_owner_email_returns_error(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post(route('books.share', $book), [
                'email' => 'owner@example.com',
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_sharing_book_with_already_shared_user_returns_error(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create(['email' => 'friend@example.com']);
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->sharedUsers()->attach($sharedUser->id);

        $response = $this->actingAs($owner)
            ->post(route('books.share', $book), [
                'email' => 'friend@example.com',
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_owner_can_revoke_shared_access(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->sharedUsers()->attach($sharedUser->id);

        $response = $this->actingAs($owner)
            ->delete(route('books.unshare', [$book, $sharedUser]));

        $response->assertRedirect();
        $this->assertFalse($book->fresh()->sharedUsers->contains($sharedUser));
        $this->assertFalse($book->fresh()->hasAccess($sharedUser));
    }

    public function test_shared_user_can_view_book_details_read_and_summaries(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id, 'file_type' => BookFileType::Pdf]);
        $book->sharedUsers()->attach($sharedUser->id);

        $this->actingAs($sharedUser)
            ->get(route('books.show', $book))
            ->assertStatus(200);

        $this->actingAs($sharedUser)
            ->get(route('books.read', $book))
            ->assertStatus(200);

        $this->actingAs($sharedUser)
            ->get(route('books.summaries', $book))
            ->assertStatus(200);
    }

    public function test_shared_user_can_visit_summary_chat_and_send_messages(): void
    {
        config(['services.openai.api_key' => '']);

        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->sharedUsers()->attach($sharedUser->id);

        $summary = Summary::factory()->create(['book_id' => $book->id]);

        $response = $this->actingAs($sharedUser)
            ->post(route('summaries.chat', $summary), [
                'message' => 'What is the main takeaway of this summary?',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('summary_chat_messages', [
            'summary_id' => $summary->id,
            'role' => 'user',
            'content' => 'What is the main takeaway of this summary?',
        ]);
    }

    public function test_non_shared_user_cannot_view_book_and_gets_403(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)
            ->get(route('books.show', $book))
            ->assertStatus(403);

        $this->actingAs($stranger)
            ->get(route('books.read', $book))
            ->assertStatus(403);
    }

    public function test_non_shared_user_cannot_view_or_chat_on_summary_and_gets_403(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $summary = Summary::factory()->create(['book_id' => $book->id]);

        $this->actingAs($stranger)
            ->get(route('books.summaries', [$book, $summary]))
            ->assertStatus(403);

        $this->actingAs($stranger)
            ->post(route('summaries.chat', $summary), [
                'message' => 'Sneaky message',
            ])
            ->assertStatus(403);
    }

    public function test_non_owner_cannot_edit_update_delete_or_share_book(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->sharedUsers()->attach($sharedUser->id);

        $this->actingAs($sharedUser)
            ->get(route('books.edit', $book))
            ->assertStatus(403);

        $this->actingAs($sharedUser)
            ->put(route('books.update', $book), [
                'title' => 'Hacked Title',
            ])
            ->assertStatus(403);

        $this->actingAs($sharedUser)
            ->delete(route('books.destroy', $book))
            ->assertStatus(403);

        $otherPerson = User::factory()->create(['email' => 'other@example.com']);
        $this->actingAs($sharedUser)
            ->post(route('books.share', $book), [
                'email' => 'other@example.com',
            ])
            ->assertStatus(403);
    }

    public function test_dashboard_and_index_only_show_owned_and_shared_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedBook = Book::factory()->create(['user_id' => $user->id, 'title' => 'My Owned Book']);
        $sharedBook = Book::factory()->create(['user_id' => $otherUser->id, 'title' => 'Shared Book With Me']);
        $privateBook = Book::factory()->create(['user_id' => $otherUser->id, 'title' => 'Private Other Book']);

        $sharedBook->sharedUsers()->attach($user->id);

        $response = $this->actingAs($user)->get(route('books.index'));

        $response->assertStatus(200);
        $booksProp = $response->inertiaProps()['books'];

        $bookIds = collect($booksProp)->pluck('id')->all();
        $this->assertContains($ownedBook->id, $bookIds);
        $this->assertContains($sharedBook->id, $bookIds);
        $this->assertNotContains($privateBook->id, $bookIds);
    }
}
