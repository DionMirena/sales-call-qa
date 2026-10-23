<?php
/**
 * End-to-end smoke test: run good_call.mp3 through the full pipeline (fake AI mode).
 * Usage: php scripts/e2e_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$samplePath = __DIR__ . '/../samples/long_call.mp3';
if (!file_exists($samplePath)) {
    echo "ERROR: samples/long_call.mp3 not found. Run scripts/make_sample_calls.py first.\n";
    exit(1);
}

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\ProcessCallJob;
use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\User;
use App\Services\ScoringService;
use App\Services\TranscriptionService;
use App\Support\AudioNormaliser;
use Illuminate\Support\Facades\Storage;

$user     = User::where('email', 'admin@example.com')->first();
$agent    = Agent::first();
$template = Template::first();

if (!$user || !$agent || !$template) {
    echo "ERROR: Run 'php artisan db:seed' first.\n";
    exit(1);
}

$stored = Storage::disk('private')->putFileAs(
    'calls/originals',
    new \Illuminate\Http\File($samplePath),
    'e2e_test_long_call.mp3'
);

$call = Call::create([
    'agent_id'          => $agent->id,
    'template_id'       => $template->id,
    'uploaded_by'       => $user->id,
    'original_path'     => $stored,
    'original_filename' => 'good_call.mp3',
    'status'            => 'queued',
]);

echo "Call ID: {$call->id}\n";
echo "Processing...\n";

$start = microtime(true);

(new ProcessCallJob($call->id))->handle(
    app(AudioNormaliser::class),
    app(TranscriptionService::class),
    app(ScoringService::class),
);

$elapsed = round(microtime(true) - $start, 1);
$call->refresh();

echo "\n=== Result ===\n";
printf("%-20s %s\n", "Status:",           $call->status);
printf("%-20s %ds\n", "Duration:",         $call->duration_seconds ?? 0);
printf("%-20s %d\n",  "Segments:",         $call->segments()->count());
printf("%-20s %.1f%%\n", "Score:",         $call->score_percent ?? 0);
printf("%-20s %s\n", "Outcome:",           $call->outcome ?? '—');
printf("%-20s %ss\n", "Processing time:",  $elapsed);

if ($call->status === 'failed') {
    echo "\nERROR: {$call->error_message}\n";
    exit(1);
}

echo "\nPASS — pipeline completed successfully.\n";
