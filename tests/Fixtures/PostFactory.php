<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;

/**
 * @extends Factory<Post>
 */
#[UseModel(class: Post::class)]
final class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, asText: true),
        ];
    }
}
