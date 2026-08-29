<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_active' => true,
        ];
    }
}
