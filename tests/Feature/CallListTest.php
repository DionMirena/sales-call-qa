<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CallListTest extends TestCase
{
    use RefreshDatabase;

    private function makeManager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function makeCall(User $manager, array $attrs = []): Call
    {
        $agent = Agent::factory()->create();
        $tpl   = Template::factory()->create(['created_by' => $manager->id]);

        return Call::create(array_merge([
            'agent_id'          => $agent->id,
            'template_id'       => $tpl->id,
            'uploaded_by'       => $manager->id,
            'original_path'     => 'calls/originals/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'done',
            'score_percent'     => 75.0,
            'outcome'           => 'fail',
            'agent_speaker'     => 0,
        ], $attrs));
    }

    public function test_list_requires_auth(): void
    {
        $this->get(route('calls.index'))->assertRedirect('/login');
    }

    public function test_calls_are_numbered_in_the_list_and_on_the_results_page(): void
    {
        $manager = $this->makeManager();
        $call    = $this->makeCall($manager, ['original_filename' => 'good_call.mp3']);

        $this->actingAs($manager)->get(route('calls.index'))
            ->assertOk()
            ->assertSee("#{$call->id}")
            ->assertSee('good_call.mp3');

        $this->actingAs($manager)->get(route('calls.show', $call))
            ->assertOk()
            ->assertSee("Call #{$call->id}");
    }

    public function test_manager_sees_only_own_calls(): void
    {
        $manager  = $this->makeManager();
        $stranger = $this->makeManager();

        $own   = $this->makeCall($manager);
        $other = $this->makeCall($stranger);

        $response = $this->actingAs($manager)->get(route('calls.index'));

        $response->assertOk();
        $ids = $response->viewData('calls')->pluck('id')->toArray();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_admin_sees_all_calls(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $manager = $this->makeManager();

        $call1 = $this->makeCall($manager);
        $call2 = $this->makeCall($manager);

        $response = $this->actingAs($admin)->get(route('calls.index'));

        $response->assertOk();
        $ids = $response->viewData('calls')->pluck('id')->toArray();
        $this->assertContains($call1->id, $ids);
        $this->assertContains($call2->id, $ids);
    }

    public function test_filter_by_outcome(): void
    {
        $manager = $this->makeManager();

        $pass = $this->makeCall($manager, ['score_percent' => 90, 'outcome' => 'pass']);
        $fail = $this->makeCall($manager, ['score_percent' => 50, 'outcome' => 'fail']);

        $response = $this->actingAs($manager)
            ->get(route('calls.index', ['outcome' => 'pass']));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('calls'));
        $this->assertEquals('pass', $response->viewData('calls')->first()->outcome);
    }

    public function test_filter_by_status(): void
    {
        $manager = $this->makeManager();

        $done   = $this->makeCall($manager, ['status' => 'done']);
        $failed = $this->makeCall($manager, ['status' => 'failed', 'score_percent' => null, 'outcome' => null]);

        $response = $this->actingAs($manager)
            ->get(route('calls.index', ['status' => 'failed']));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('calls'));
        $this->assertEquals('failed', $response->viewData('calls')->first()->status);
    }

    public function test_filter_by_date_from(): void
    {
        $manager = $this->makeManager();

        $old = $this->makeCall($manager);
        // Bypass Eloquent to backdate created_at
        DB::table('calls')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);
        $new = $this->makeCall($manager);

        $response = $this->actingAs($manager)
            ->get(route('calls.index', ['date_from' => now()->subDay()->toDateString()]));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('calls'));
        $this->assertEquals($new->id, $response->viewData('calls')->first()->id);
    }

    public function test_filter_by_score_min(): void
    {
        $manager = $this->makeManager();
        $low  = $this->makeCall($manager, ['score_percent' => 50.0]);
        $high = $this->makeCall($manager, ['score_percent' => 90.0]);

        $ids = $this->actingAs($manager)
            ->get(route('calls.index', ['score_min' => 80]))
            ->viewData('calls')->pluck('id')->toArray();

        $this->assertContains($high->id, $ids);
        $this->assertNotContains($low->id, $ids);
    }

    public function test_filter_by_score_max(): void
    {
        $manager = $this->makeManager();
        $low  = $this->makeCall($manager, ['score_percent' => 50.0]);
        $high = $this->makeCall($manager, ['score_percent' => 90.0]);

        $ids = $this->actingAs($manager)
            ->get(route('calls.index', ['score_max' => 70]))
            ->viewData('calls')->pluck('id')->toArray();

        $this->assertContains($low->id, $ids);
        $this->assertNotContains($high->id, $ids);
    }

    public function test_filter_needs_review_only(): void
    {
        $manager  = $this->makeManager();
        $clean    = $this->makeCall($manager);
        $flagged  = $this->makeCall($manager);
        $flagged->criterionResults()->create([
            'criterion_name'        => 'C', 'criterion_description' => 'D',
            'criterion_type'        => 'pass_fail', 'criterion_weight' => 1,
            'criterion_is_critical' => false, 'ai_result' => 'fail', 'ai_score' => 0.0,
            'confidence' => 0.3, 'reasoning' => 'r', 'evidence' => [],
            'verification_status' => 'unverified', 'needs_review' => true,
        ]);

        $ids = $this->actingAs($manager)
            ->get(route('calls.index', ['needs_review' => '1']))
            ->viewData('calls')->pluck('id')->toArray();

        $this->assertContains($flagged->id, $ids);
        $this->assertNotContains($clean->id, $ids);
    }

    public function test_results_are_paginated(): void
    {
        $manager = $this->makeManager();
        for ($i = 0; $i < 3; $i++) {
            $this->makeCall($manager);
        }

        $response = $this->actingAs($manager)->get(route('calls.index'));
        $response->assertOk();
        $this->assertCount(3, $response->viewData('calls'));
    }
}
