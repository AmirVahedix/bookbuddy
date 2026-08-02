<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource;
use App\Filament\Resources\SummaryResource\Pages\ListSummaries;
use App\Filament\Resources\SummaryResource\Pages\ViewSummary;
use App\Filament\Resources\UserResource;
use App\Models\Book;
use App\Models\BookSection;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_can_list_users_in_filament(): void
    {
        $users = User::factory()->count(3)->create();

        Livewire::actingAs($this->admin)
            ->test(UserResource\Pages\ListUsers::class)
            ->assertCanSeeTableRecords($users);
    }

    public function test_can_create_user_in_filament(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UserResource\Pages\CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'secret123',
                'is_admin' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_can_list_books_in_filament(): void
    {
        $book = Book::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Sample Book Title',
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookResource\Pages\ListBooks::class)
            ->assertCanSeeTableRecords([$book]);
    }

    public function test_can_render_sections_relation_manager_for_book(): void
    {
        $book = Book::factory()->create(['user_id' => $this->admin->id]);
        $section = BookSection::factory()->create([
            'book_id' => $book->id,
            'title' => 'Chapter One',
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookResource\RelationManagers\SectionsRelationManager::class, [
                'ownerRecord' => $book,
                'pageClass' => BookResource\Pages\EditBook::class,
            ])
            ->assertCanSeeTableRecords([$section]);
    }

    public function test_can_list_and_view_summaries(): void
    {
        $book = Book::factory()->create(['user_id' => $this->admin->id]);
        $summary = Summary::factory()->create([
            'book_id' => $book->id,
            'generated_summary' => 'This is a test summary of the book.',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListSummaries::class)
            ->assertCanSeeTableRecords([$summary]);

        Livewire::actingAs($this->admin)
            ->test(ViewSummary::class, [
                'record' => $summary->getRouteKey(),
            ])
            ->assertSuccessful();
    }
}
