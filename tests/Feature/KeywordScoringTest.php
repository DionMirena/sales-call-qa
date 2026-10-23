<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\KeywordScoringService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordScoringTest extends TestCase
{
    use RefreshDatabase;

    /** Real Deepgram transcript of samples/good_call.mp3 (agent = speaker 0). */
    private const GOOD_CALL = [
        [0, 279, 'Hello. This is Sarah Mitchell from Clear view life insurance.'],
        [0, 4343, 'Just to let you know, this call may be recorded for training and quality purposes.'],
        [0, 9522, 'Is that okay?'],
        [1, 11116, "Sure. That's fine."],
        [0, 14482, "I'm calling today because you recently submitted an inquiry on our website about life insurance cover. I'd love to help you find the right policy."],
        [0, 33631, 'could you tell me your age?'],
        [1, 35620, "I'm 38."],
        [0, 37704, 'Thank you. And do you have any existing life insurance cover at the moment?'],
        [1, 42804, 'No. Nothing at all.'],
        [0, 48381, 'And do you have children or other dependents who rely on your income?'],
        [1, 52860, 'Yes, two kids, seven and nine.'],
        [0, 58616, 'And are there any significant health conditions I should be aware of, like diabetes,'],
        [1, 66131, "No. I'm generally pretty healthy."],
        [0, 74527, 'and roughly what kind of monthly premium are you comfortable with?'],
        [1, 78596, 'I was thinking somewhere between 30 and £50 a month.'],
        [0, 84274, "Based on what you've told me, I'd suggest a level term life policy for five hundred thousand pounds of cover over a twenty five year term, which would see your youngest through to their thirties."],
        [0, 94654, 'With your age and health profile, that would typically come in around 32 to £36'],
        [0, 99912, 'a month. Would that work for you? That sounds about right. Yes.'],
        [0, 106860, 'I do want to be clear that the final premium will be confirmed after underwriting, which just means they may ask a few more health questions.'],
        [0, 114454, "It's not guaranteed to be approved at that rate, but based on what you've said it should be straightforward."],
        [0, 123105, 'Also, once the policy is issued, you have a fourteen day cooling off period where you can cancel and receive a full refund, no questions asked.'],
        [0, 134557, "So the next step is I'll send you a summary email with all the details and a link to start the application. The underwriting team will follow up within two working days. Does that all sound good?"],
        [1, 146374, 'Yes. Perfect.'],
        [0, 150615, 'Thank you for your time. Have a lovely day.'],
    ];

    private function scoreCall(array $lines): Call
    {
        $user = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->seed(TemplateSeeder::class);

        $call = Call::create([
            'agent_id'          => Agent::factory()->create()->id,
            'template_id'       => Template::firstOrFail()->id,
            'uploaded_by'       => $user->id,
            'original_path'     => 'x',
            'original_filename' => 'x.mp3',
            'status'            => 'scoring',
            'agent_speaker'     => 0,
        ]);

        foreach ($lines as $i => [$speaker, $start, $text]) {
            TranscriptSegment::create([
                'call_id'  => $call->id,
                'code'     => 'S' . ($i + 1),
                'speaker'  => $speaker,
                'start_ms' => $start,
                'end_ms'   => $start + 3000,
                'text'     => $text,
            ]);
        }

        (new KeywordScoringService)->score($call);

        return $call->fresh('criterionResults');
    }

    private function results(Call $call): array
    {
        return $call->criterionResults->mapWithKeys(fn ($r) => [$r->criterion_name => $r->ai_result])->all();
    }

    public function test_good_call_passes_every_criterion_with_real_evidence(): void
    {
        $call = $this->scoreCall(self::GOOD_CALL);
        $results = $this->results($call);

        $this->assertSame('done', $call->status);
        $this->assertSame('pass', $call->outcome);
        $this->assertCount(13, $results);

        foreach ($call->criterionResults as $r) {
            if ($r->criterion_type === 'extract') {
                $this->assertSame('32 to £36 a month', $r->ai_result);
            } elseif ($r->criterion_type !== 'scale_1_5') {
                $this->assertContains($r->ai_result, ['pass', 'yes'], $r->criterion_name . ' should pass');
            }
        }

        $identification = $call->criterionResults->firstWhere('criterion_name', 'Opening – Agent Identification');
        $this->assertSame('S1', $identification->evidence[0]['segment_code']);
    }

    public function test_missing_disclosure_and_pressure_language_fail(): void
    {
        $lines = self::GOOD_CALL;
        unset($lines[1]); // drop "this call may be recorded"
        $lines[] = [0, 152000, 'You really need to decide today, this price is your last chance.'];

        $results = $this->results($call = $this->scoreCall(array_values($lines)));

        $this->assertSame('no', $results['Opening – Call Recording Disclosure']);
        $this->assertSame('no', $results['Compliance – No Pressure Tactics']);
        $this->assertSame('auto_fail', $call->outcome);
    }

    public function test_negated_guarantee_and_reassurance_are_not_flagged(): void
    {
        $lines   = self::GOOD_CALL;
        $lines[] = [0, 152000, "There's no rush to decide today."];

        $results = $this->results($this->scoreCall($lines));

        $this->assertSame('yes', $results['Compliance – No Guaranteed-Approval Claims']);
        $this->assertSame('yes', $results['Compliance – No Pressure Tactics']);
    }

    public function test_recognises_currently_bare_amounts_and_spelled_out_terms(): void
    {
        $lines = self::GOOD_CALL;
        $lines[7]  = [0, 37704, 'And do you currently have any life insurance yourself, either at work or privately?'];
        $lines[15] = [0, 84274, 'Option A, a decreasing term policy, 160,000 over twelve years to match the mortgage.'];

        $results = $this->results($this->scoreCall($lines));

        $this->assertSame('pass', $results['Needs Discovery – Existing Cover and Dependants']);
        $this->assertSame('pass', $results['Product Explanation – Coverage Amount and Term']);
    }
}
