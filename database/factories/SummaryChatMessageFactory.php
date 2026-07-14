<?php

namespace Database\Factories;

use App\Models\Summary;
use App\Models\SummaryChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SummaryChatMessage>
 */
class SummaryChatMessageFactory extends Factory
{
    protected $model = SummaryChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'summary_id' => Summary::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant']),
            'content' => $this->faker->paragraph(),
            'tokens_used' => $this->faker->numberBetween(100, 1000),
        ];
    }
}
