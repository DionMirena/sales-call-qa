<?php

namespace Database\Seeders;

use App\Jobs\ProcessCallJob;
use App\Models\Call;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds 10 sample calls from samples/ directory, spread over the last 3 weeks.
 * Run: php artisan db:seed --class=SampleCallSeeder
 * Then: php artisan queue:work --stop-when-empty --timeout=600
 */
class SampleCallSeeder extends Seeder
{
    // [name, agent_id, created_at offset in days from today]
    private const CALLS = [
        // Week 3 ago (Oct 2 ≈ -21 days)
        ['compliant_full',       3, -21],
        ['objection_handling',   2, -20],
        ['near_perfect',         1, -19],
        ['no_recording_notice',  5, -18],

        // Week 2 ago (Oct 9 ≈ -14 days)
        ['guaranteed_approval_2', 4, -14],
        ['pressure_subtle',       3, -13],
        ['no_cooling_off',        2, -12],

        // Week 1 ago (Oct 16 ≈ -7 days)
        ['no_next_steps',        1, -7],
        ['incomplete_discovery', 5, -6],
        ['thorough_discovery',   4, -5],
    ];

    public function run(): void
    {
        $templateId = DB::table('templates')->value('id');
        $adminId    = DB::table('users')->where('role', 'admin')->value('id');

        foreach (self::CALLS as [$name, $agentId, $daysOffset]) {
            $src = base_path("samples/{$name}.mp3");

            if (! file_exists($src)) {
                $this->command->warn("Skipping {$name}: samples/{$name}.mp3 not found");
                continue;
            }

            $originalPath = "calls/originals/{$name}.mp3";
            Storage::disk('private')->put($originalPath, file_get_contents($src));

            $call = Call::create([
                'agent_id'          => $agentId,
                'template_id'       => $templateId,
                'uploaded_by'       => $adminId,
                'original_path'     => $originalPath,
                'original_filename' => "{$name}.mp3",
                'status'            => 'queued',
            ]);

            $createdAt = now()->addDays($daysOffset)->toDateTimeString();
            DB::table('calls')->where('id', $call->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);

            ProcessCallJob::dispatch($call->id);
            $this->command->info("Queued call #{$call->id}: {$name} (agent {$agentId}, {$daysOffset}d)");
        }

        $this->command->info('All 10 calls queued. Run: php artisan queue:work --stop-when-empty --timeout=600');
    }
}
