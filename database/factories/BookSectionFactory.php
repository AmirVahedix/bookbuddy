<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookSection>
 */
class BookSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chapterNum = fake()->numberBetween(1, 15);
        $startPage = fake()->numberBetween(1, 400);

        return [
            'book_id' => Book::factory(),
            'title' => "Chapter {$chapterNum}: ".fake()->sentence(4),
            'section_identifier' => "OEBPS/xhtml/chapter{$chapterNum}.xhtml",
            'start_page' => $startPage,
            'end_page' => $startPage + fake()->numberBetween(10, 50),
            'order' => $chapterNum,
        ];
    }
}
