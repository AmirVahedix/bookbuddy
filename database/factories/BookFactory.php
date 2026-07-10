<?php

namespace Database\Factories;

use App\Enums\BookFileType;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $books = [
            ['title' => 'Designing Data-Intensive Applications', 'author' => 'Martin Kleppmann'],
            ['title' => 'Structure and Interpretation of Computer Programs', 'author' => 'Harold Abelson'],
            ['title' => 'Introduction to Algorithms', 'author' => 'Thomas H. Cormen'],
            ['title' => 'Clean Code: A Handbook of Agile Software Craftsmanship', 'author' => 'Robert C. Martin'],
            ['title' => 'The Selfish Gene', 'author' => 'Richard Dawkins'],
            ['title' => 'A Brief History of Time', 'author' => 'Stephen Hawking'],
            ['title' => 'Artificial Intelligence: A Modern Approach', 'author' => 'Stuart Russell'],
            ['title' => 'Introduction to Quantum Mechanics', 'author' => 'David J. Griffiths'],
            ['title' => 'The Elegant Universe', 'author' => 'Brian Greene'],
            ['title' => 'Astrophysics for People in a Hurry', 'author' => 'Neil deGrasse Tyson'],
        ];

        $book = fake()->randomElement($books);
        $fileType = fake()->randomElement(BookFileType::cases());
        $totalPages = fake()->numberBetween(100, 1000);

        return [
            'title' => $book['title'],
            'author' => $book['author'],
            'file_type' => $fileType,
            'file_path' => 'books/'.Str::slug($book['title']).'.'.$fileType->value,
            'total_pages' => $totalPages,
            'current_page' => fake()->numberBetween(0, $totalPages),
        ];
    }
}
