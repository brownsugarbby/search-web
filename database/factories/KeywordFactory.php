<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KeywordFactory extends Factory
{
    public function definition(): array
    {
        // keyword_normalized is derived on save by the model, never set here -
        // that is the whole point of normalising in one place.
        return [
            'keyword' => fake()->unique()->words(2, true),
        ];
    }
}
