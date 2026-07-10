<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Summary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Summary>
 */
class SummaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $targetPages = fake()->boolean(70)
            ? range(fake()->numberBetween(1, 50), fake()->numberBetween(51, 100))
            : null;

        $markdown = <<<'MARKDOWN'
# Key Architectural Insights

This section covers the core concepts of building highly reliable, scalable, and maintainable systems.

## Summary of Key Takeaways
1. **Reliability**: Systems should continue to work correctly, even when things go wrong.
2. **Scalability**: Strategies to handle growth (load parameters, throughput, response times).
3. **Maintainability**: Designing code so that many different people can work on it productively.

## Technical Details
- **SSTables and LSM-Trees**: Log-Structured Merge-Trees are optimized for high write throughput.
- **B-Trees**: The most widely used index structure, storing data in fixed-size blocks/pages.
- **Indexes**: Keeping index data structures in memory to speed up key-value lookups.
MARKDOWN;

        return [
            'book_id' => Book::factory(),
            'book_section_id' => null,
            'target_pages' => $targetPages,
            'prompt_used' => 'Analyze the architectural patterns in this chapter and summarize the key takeaways, focusing on B-Trees vs LSM-Trees.',
            'generated_summary' => $markdown,
            'tokens_used' => fake()->numberBetween(500, 3000),
        ];
    }
}
