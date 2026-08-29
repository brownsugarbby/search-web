<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LinkFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title' => $title,
            'url' => fake()->url(),
            'description' => fake()->sentence(12),
            'weight' => 0,
            'is_active' => true,
            'is_reviewed' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unreviewed(): static
    {
        return $this->state(fn () => ['is_reviewed' => false]);
    }
}
