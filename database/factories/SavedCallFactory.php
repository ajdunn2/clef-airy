<?php

namespace Database\Factories;

use App\Models\SavedCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedCall>
 */
class SavedCallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'method' => 'POST',
            'path' => '/v1/systemone',
            'model' => 'clef-flash',
            'body_mode' => 'form',
            'state' => fake()->sentence(),
            'questions' => [
                [
                    'name' => 'urgent',
                    'type' => 'noul',
                    'instructions' => 'Is this urgent?',
                    'true' => '',
                    'false' => '',
                    'options' => [],
                    'levels' => [],
                ],
            ],
            'body' => null,
        ];
    }
}
