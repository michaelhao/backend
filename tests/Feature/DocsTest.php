<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('docs.index'))->assertRedirect(route('login'));
        $this->get(route('docs.show', 'auth-spec'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_see_docs_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('docs.index'));

        $response->assertStatus(200);
        $response->assertSee('系統文件');
        $response->assertSee('auth-spec.html');
        $response->assertSee('auth Specification');
    }

    public function test_authenticated_user_can_view_a_doc(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('docs.show', 'auth-spec'));

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/html', $response->headers->get('content-type'));
    }

    public function test_unknown_doc_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('docs.show', 'nonexistent'))->assertNotFound();
    }

    public function test_path_traversal_is_rejected_by_route_constraint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/docs/..%2F.env')->assertNotFound();
    }
}
