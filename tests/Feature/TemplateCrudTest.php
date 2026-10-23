<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function criteriaPayload(int $count = 2): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'name'            => "Criterion $i",
                'description'     => "Description $i",
                'fail_conditions' => '',
                'type'            => 'pass_fail',
                'weight'          => 2,
                'is_critical'     => false,
                'phase_hint'      => 'anywhere',
            ];
        }
        return $rows;
    }

    // ── List ───────────────────────────────────────────────────────────────
    public function test_index_requires_authentication(): void
    {
        $this->get(route('templates.index'))->assertRedirect('/login');
    }

    public function test_manager_can_view_template_list(): void
    {
        $this->actingAs($this->manager())
            ->get(route('templates.index'))
            ->assertOk();
    }

    // ── Create ─────────────────────────────────────────────────────────────
    public function test_manager_can_create_template_with_criteria(): void
    {
        $manager = $this->manager();

        $response = $this->actingAs($manager)->post(route('templates.store'), [
            'name'           => 'My Template',
            'description'    => 'Test description',
            'pass_threshold' => 75,
            'criteria'       => $this->criteriaPayload(3),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('templates', ['name' => 'My Template', 'created_by' => $manager->id]);
        $this->assertDatabaseCount('criteria', 3);
    }

    public function test_store_fails_without_criteria(): void
    {
        $this->actingAs($this->manager())
            ->post(route('templates.store'), [
                'name'           => 'Empty',
                'pass_threshold' => 80,
                'criteria'       => [],
            ])
            ->assertSessionHasErrors('criteria');
    }

    public function test_store_validates_criterion_type(): void
    {
        $rows = $this->criteriaPayload(1);
        $rows[0]['type'] = 'invalid_type';

        $this->actingAs($this->manager())
            ->post(route('templates.store'), [
                'name'           => 'Bad',
                'pass_threshold' => 80,
                'criteria'       => $rows,
            ])
            ->assertSessionHasErrors('criteria.0.type');
    }

    // ── Edit / Update ──────────────────────────────────────────────────────
    public function test_manager_can_edit_own_template(): void
    {
        $manager  = $this->manager();
        $template = Template::factory()->create(['created_by' => $manager->id]);
        $template->criteria()->create([
            'position' => 0, 'name' => 'Old', 'description' => 'Old', 'type' => 'pass_fail',
            'weight' => 1, 'is_critical' => false, 'phase_hint' => 'anywhere',
        ]);

        $this->actingAs($manager)->put(route('templates.update', $template), [
            'name'           => 'Updated',
            'pass_threshold' => 90,
            'criteria'       => $this->criteriaPayload(2),
        ])->assertRedirect();

        $this->assertDatabaseHas('templates', ['id' => $template->id, 'name' => 'Updated']);
        $this->assertEquals(2, $template->fresh()->criteria()->count());
    }

    public function test_manager_cannot_edit_another_managers_template(): void
    {
        $owner   = $this->manager();
        $other   = $this->manager();
        $template = Template::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($other)
            ->get(route('templates.edit', $template))
            ->assertForbidden();
    }

    public function test_admin_can_edit_any_template(): void
    {
        $owner    = $this->manager();
        $template = Template::factory()->create(['created_by' => $owner->id]);
        $template->criteria()->create([
            'position' => 0, 'name' => 'C', 'description' => 'D', 'type' => 'pass_fail',
            'weight' => 1, 'is_critical' => false, 'phase_hint' => 'anywhere',
        ]);

        $this->actingAs($this->admin())
            ->put(route('templates.update', $template), [
                'name'           => 'Admin updated',
                'pass_threshold' => 80,
                'criteria'       => $this->criteriaPayload(1),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('templates', ['id' => $template->id, 'name' => 'Admin updated']);
    }

    // ── Delete ─────────────────────────────────────────────────────────────
    public function test_manager_can_delete_own_template(): void
    {
        $manager  = $this->manager();
        $template = Template::factory()->create(['created_by' => $manager->id]);

        $this->actingAs($manager)
            ->delete(route('templates.destroy', $template))
            ->assertRedirect(route('templates.index'));

        $this->assertSoftDeleted('templates', ['id' => $template->id]);
    }

    public function test_manager_cannot_delete_another_managers_template(): void
    {
        $owner    = $this->manager();
        $other    = $this->manager();
        $template = Template::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($other)
            ->delete(route('templates.destroy', $template))
            ->assertForbidden();
    }

    // ── Duplicate ──────────────────────────────────────────────────────────
    public function test_manager_can_duplicate_template(): void
    {
        $manager  = $this->manager();
        $template = Template::factory()->create(['created_by' => $manager->id]);
        $template->criteria()->create([
            'position' => 0, 'name' => 'C1', 'description' => 'D1', 'type' => 'pass_fail',
            'weight' => 1, 'is_critical' => false, 'phase_hint' => 'anywhere',
        ]);

        $this->actingAs($manager)
            ->post(route('templates.duplicate', $template))
            ->assertRedirect();

        $this->assertEquals(2, Template::count());
        $this->assertDatabaseHas('templates', ['name' => $template->name . ' (copy)']);
        $this->assertEquals(2, \App\Models\Criterion::count());
    }
}
