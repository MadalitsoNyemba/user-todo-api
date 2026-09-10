<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TodoPriority;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Enough volume and variety for Alice that list/filter behaviour is obvious by
 * hand, and a small set for Bob so tenant isolation is checkable: log in as
 * Alice, note a todo id, log in as Bob, request that id, receive a 404.
 */
class TodoSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $bob = User::query()->where('email', 'bob@example.com')->firstOrFail();

        if (Todo::query()->where('user_id', $alice->id)->exists()) {
            return;
        }

        Todo::factory()->for($alice)->high()->create([
            'title' => 'Ship profile endpoints',
            'description' => 'GET/PATCH/DELETE /api/v1/me behind JWT.',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        Todo::factory()->for($alice)->medium()->create([
            'title' => 'Document OpenAPI for todos',
            'description' => null,
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        Todo::factory()->for($alice)->low()->create([
            'title' => 'Tidy README seed table',
            'due_date' => null,
        ]);

        Todo::factory()->for($alice)->completed()->high()->create([
            'title' => 'Wire JWT blacklist logout',
            'description' => 'Already merged on develop.',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        Todo::factory()->for($alice)->completed()->medium()->create([
            'title' => 'Add l5-swagger docs route',
            'due_date' => now()->subWeek()->toDateString(),
        ]);

        Todo::factory()->for($alice)->overdue()->high()->create([
            'title' => 'Fix flaky CI JWT secret step',
            'description' => 'Past due — still worth a look if it regresses.',
        ]);

        Todo::factory()->for($alice)->overdue()->low()->create([
            'title' => 'Reply to mentor feedback thread',
        ]);

        Todo::factory()->for($alice)->count(3)->high()->create();
        Todo::factory()->for($alice)->count(3)->medium()->create();
        Todo::factory()->for($alice)->count(2)->low()->completed()->create();

        Todo::factory()->for($bob)->high()->create([
            'title' => 'Bob private: rotate API keys',
            'description' => 'Must not appear in Alice’s list.',
            'due_date' => now()->addDays(4)->toDateString(),
            'priority' => TodoPriority::High,
        ]);

        Todo::factory()->for($bob)->completed()->create([
            'title' => 'Bob private: read brief',
        ]);

        Todo::factory()->for($bob)->overdue()->medium()->create([
            'title' => 'Bob private: overdue invoice check',
        ]);

        Todo::factory()->for($bob)->low()->create([
            'title' => 'Bob private: backlog grooming',
            'due_date' => null,
            'priority' => TodoPriority::Low,
        ]);
    }
}
