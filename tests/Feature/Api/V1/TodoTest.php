<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TodoPriority;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_create_and_list_todos(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos', [
                'title' => 'Write the brief',
                'description' => 'Keep it short.',
                'priority' => 'high',
                'due_date' => '2030-01-15',
            ]);

        $create->assertStatus(201);
        $create->assertJsonPath('success', true);
        $create->assertJsonPath('data.title', 'Write the brief');
        $create->assertJsonPath('data.priority', 'high');
        $create->assertJsonMissingPath('data.user_id');

        $list = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos');

        $list->assertOk();
        $list->assertJsonPath('success', true);
        $list->assertJsonPath('meta.current_page', 1);
        $list->assertJsonPath('meta.per_page', 15);
        $list->assertJsonPath('meta.total', 1);
        $list->assertJsonCount(1, 'data');
    }

    public function test_filters_sort_and_per_page_cap_apply(): void
    {
        [$user, $token] = $this->userWithToken('alice@example.com');

        Todo::factory()->for($user)->high()->create(['title' => 'High open', 'is_completed' => false]);
        Todo::factory()->for($user)->low()->completed()->create(['title' => 'Low done']);
        Todo::factory()->for($user)->medium()->create([
            'title' => 'Due soon',
            'due_date' => '2030-02-01',
            'is_completed' => false,
        ]);

        $filtered = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?is_completed=0&priority=high');

        $filtered->assertOk();
        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.title', 'High open');

        $sorted = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?sort=due_date&order=asc');

        $sorted->assertOk();
        $titles = collect($sorted->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Due soon'));

        $capped = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?per_page=100000');

        $capped->assertOk();
        $capped->assertJsonPath('meta.per_page', 100);
    }

    public function test_the_owner_can_show_update_complete_reopen_and_delete(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $id = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos', [
                'title' => 'Toggle me',
                'priority' => TodoPriority::Medium->value,
            ])
            ->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/todos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $completed = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/todos/{$id}", [
                'is_completed' => true,
            ]);

        $completed->assertOk();
        $completed->assertJsonPath('data.is_completed', true);
        $this->assertNotNull($completed->json('data.completed_at'));

        $reopened = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/todos/{$id}", [
                'is_completed' => false,
                'title' => 'Still open',
            ]);

        $reopened->assertOk();
        $reopened->assertJsonPath('data.is_completed', false);
        $reopened->assertJsonPath('data.completed_at', null);
        $reopened->assertJsonPath('data.title', 'Still open');

        $delete = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/todos/{$id}");

        $delete->assertOk();
        $delete->assertJsonPath('success', true);
        $delete->assertJsonPath('data', null);
        $delete->assertJsonPath('message', 'Todo deleted.');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/todos/{$id}")
            ->assertStatus(404);
    }

    public function test_another_users_todo_returns_404_not_403(): void
    {
        [, $aliceToken] = $this->userWithToken('alice@example.com');
        [$bob] = $this->userWithToken('bob@example.com');

        $bobTodo = Todo::factory()->for($bob)->create(['title' => 'Bob only']);

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
                ->json($method, "/api/v1/todos/{$bobTodo->id}", [
                    'title' => 'hijack',
                ]);

            $response->assertStatus(404);
            $response->assertJsonPath('success', false);
            $response->assertJsonPath('message', 'Resource not found.');
            $this->assertNotSame(403, $response->status());
        }

        $this->assertDatabaseHas('todos', ['id' => $bobTodo->id, 'title' => 'Bob only']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/todos')->assertStatus(401);
        $this->postJson('/api/v1/todos', ['title' => 'Nope'])->assertStatus(401);
        $this->getJson('/api/v1/todos/1')->assertStatus(401);
        $this->patchJson('/api/v1/todos/1', ['title' => 'Nope'])->assertStatus(401);
        $this->deleteJson('/api/v1/todos/1')->assertStatus(401);
    }

    public function test_store_rejects_an_invalid_priority(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos', [
                'title' => 'Bad priority',
                'priority' => 'urgent',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['priority']]);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function userWithToken(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $login->assertOk();
        $token = $login->json('data.token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        return [$user, $token];
    }
}
