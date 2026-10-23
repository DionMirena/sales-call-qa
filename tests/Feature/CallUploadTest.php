<?php

namespace Tests\Feature;

use App\Jobs\ProcessCallJob;
use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\User;
use App\Services\ScoringService;
use App\Services\TranscriptionService;
use App\Support\AudioNormaliser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CallUploadTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function makeTemplate(User $user): Template
    {
        $t = Template::factory()->create(['created_by' => $user->id]);
        $t->criteria()->create([
            'position' => 0, 'name' => 'Test', 'description' => 'Test criterion',
            'type' => 'pass_fail', 'weight' => 1, 'is_critical' => false, 'phase_hint' => 'anywhere',
        ]);
        return $t;
    }

    // ── Upload validation ───────────────────────────────────────────────────
    public function test_upload_form_requires_auth(): void
    {
        $this->get(route('calls.create'))->assertRedirect('/login');
    }

    public function test_valid_mp3_upload_queues_job(): void
    {
        Queue::fake();
        Storage::fake('private');

        $manager  = $this->manager();
        $template = $this->makeTemplate($manager);
        $agent    = Agent::factory()->create();

        $this->actingAs($manager)->post(route('calls.store'), [
            'template_id' => $template->id,
            'agent_id'    => $agent->id,
            'audio'       => UploadedFile::fake()->create('call.mp3', 500, 'audio/mpeg'),
        ])->assertRedirect();

        Queue::assertPushed(ProcessCallJob::class);
        $this->assertDatabaseHas('calls', ['agent_id' => $agent->id, 'status' => 'queued']);
    }

    public function test_unsupported_mime_type_is_rejected(): void
    {
        Queue::fake();
        Storage::fake('private');

        $manager  = $this->manager();
        $template = $this->makeTemplate($manager);
        $agent    = Agent::factory()->create();

        $this->actingAs($manager)->post(route('calls.store'), [
            'template_id' => $template->id,
            'agent_id'    => $agent->id,
            'audio'       => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('audio');

        Queue::assertNothingPushed();
    }

    public function test_missing_template_is_rejected(): void
    {
        $manager = $this->manager();
        $agent   = Agent::factory()->create();

        $this->actingAs($manager)->post(route('calls.store'), [
            'template_id' => 9999,
            'agent_id'    => $agent->id,
            'audio'       => UploadedFile::fake()->create('call.mp3', 100, 'audio/mpeg'),
        ])->assertSessionHasErrors('template_id');
    }

    // ── ProcessCallJob ──────────────────────────────────────────────────────
    public function test_job_stores_segments_from_transcription_fixture(): void
    {
        Storage::fake('private');

        $manager  = $this->manager();
        $template = $this->makeTemplate($manager);
        $agent    = Agent::factory()->create();

        // Store a fake audio file so the normaliser has something to read
        Storage::disk('private')->put('calls/originals/test.mp3', 'fake-audio-content');

        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $template->id,
            'uploaded_by'       => $manager->id,
            'original_path'     => 'calls/originals/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'queued',
        ]);

        // Mock AudioNormaliser to skip real ffmpeg
        $this->mock(AudioNormaliser::class, function ($mock) {
            $mock->shouldReceive('normalise')->once()->andReturnUsing(function ($in, $out) {
                // Create a fake output file so the job thinks normalisation succeeded
                @mkdir(dirname($out), 0755, true);
                file_put_contents($out, 'fake-mp3');
            });
        });

        // Inject fake transcription (3 segments from the fixture)
        $this->app->bind(TranscriptionService::class, function () {
            return new class implements TranscriptionService {
                public function transcribe(string $path): array
                {
                    return [
                        'duration_seconds' => 18,
                        'segments' => [
                            ['speaker' => 0, 'start_ms' => 1000,  'end_ms' => 8000,  'text' => 'Hello, this is Sarah Mitchell from Clearview Life Insurance. Just to let you know, this call may be recorded.'],
                            ['speaker' => 1, 'start_ms' => 8500,  'end_ms' => 10000, 'text' => "Sure, that's fine."],
                            ['speaker' => 0, 'start_ms' => 10500, 'end_ms' => 18000, 'text' => 'Could you tell me your age?'],
                        ],
                    ];
                }
            };
        });

        // Stub scoring so the job completes
        $this->app->bind(ScoringService::class, function () {
            return new class implements ScoringService {
                public function score(\App\Models\Call $call): void
                {
                    $call->update(['status' => 'done', 'score_percent' => 100, 'outcome' => 'pass']);
                }
            };
        });

        (new ProcessCallJob($call->id))->handle(
            app(AudioNormaliser::class),
            app(TranscriptionService::class),
            app(ScoringService::class),
        );

        $this->assertEquals(3, $call->fresh()->segments()->count());
        $this->assertEquals('S1', $call->segments()->first()->code);
        $this->assertEquals(0, $call->segments()->first()->speaker);
        $this->assertEquals('done', $call->fresh()->status);
    }

    public function test_job_failure_sets_status_failed(): void
    {
        Storage::fake('private');

        $manager  = $this->manager();
        $template = $this->makeTemplate($manager);
        $agent    = Agent::factory()->create();

        Storage::disk('private')->put('calls/originals/test.mp3', 'fake');

        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $template->id,
            'uploaded_by'       => $manager->id,
            'original_path'     => 'calls/originals/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'queued',
        ]);

        $this->mock(AudioNormaliser::class, function ($mock) {
            $mock->shouldReceive('normalise')->andThrow(new \RuntimeException('ffmpeg not found'));
        });

        try {
            (new ProcessCallJob($call->id))->handle(
                app(AudioNormaliser::class),
                app(TranscriptionService::class),
                app(ScoringService::class),
            );
        } catch (\RuntimeException) {
            // Expected — job rethrows after marking failed
        }

        $this->assertEquals('failed', $call->fresh()->status);
        $this->assertStringContainsString('ffmpeg not found', $call->fresh()->error_message);
    }
}
