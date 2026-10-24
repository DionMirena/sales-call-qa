<?php

namespace Tests\Unit;

use App\Jobs\ProcessCallJob;
use Tests\TestCase;

class AgentSpeakerGuessTest extends TestCase
{
    private function seg(int $speaker, string $text): array
    {
        return ['speaker' => $speaker, 'text' => $text, 'start_ms' => 0, 'end_ms' => 1000];
    }

    public function test_falls_back_to_first_speaker(): void
    {
        $segments = [
            $this->seg(1, 'Hello there.'),
            $this->seg(0, 'Hi, how can I help?'),
        ];

        $this->assertEquals(1, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_prefers_speaker_with_my_name_is(): void
    {
        $segments = [
            $this->seg(1, 'Hello.'),
            $this->seg(0, 'Hi, my name is Sarah calling from INSURVAS.'),
        ];

        $this->assertEquals(0, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_prefers_speaker_with_this_is(): void
    {
        $segments = [
            $this->seg(1, 'Hello.'),
            $this->seg(0, 'Good morning, this is James from the protection team.'),
        ];

        $this->assertEquals(0, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_prefers_speaker_with_company_name(): void
    {
        config(['qa.company_name' => 'ShieldLife']);

        $segments = [
            $this->seg(1, 'Hi there.'),
            $this->seg(0, 'Good afternoon, you are through to ShieldLife insurance.'),
        ];

        $this->assertEquals(0, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_ignores_company_name_when_empty(): void
    {
        config(['qa.company_name' => '']);

        // Without a company-name match, falls back to first speaker
        $segments = [
            $this->seg(1, 'Hello this is me.'),
        ];

        $this->assertEquals(1, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_only_looks_at_first_ten_segments(): void
    {
        $segments = array_merge(
            array_fill(0, 10, $this->seg(1, 'Normal line.')),
            [$this->seg(0, 'My name is Sarah — segment 11.')],
        );

        // Segment 11 is outside the window; first speaker (1) should win
        $this->assertEquals(1, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_customer_answering_with_their_name_is_not_the_agent(): void
    {
        config(['qa.company_name' => 'INSURVAS']);

        $segments = [
            $this->seg(1, 'Hello, this is Tom.'),
            $this->seg(0, 'Hi Tom, I am calling from INSURVAS Insurance about your enquiry.'),
        ];

        $this->assertEquals(0, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_casual_this_is_does_not_count_as_an_introduction(): void
    {
        config(['qa.company_name' => '']);

        $segments = [
            $this->seg(1, 'Thanks for calling back.'),
            $this->seg(0, 'Oh this is great timing actually.'),
        ];

        $this->assertEquals(1, ProcessCallJob::guessAgentSpeaker($segments));
    }

    public function test_returns_zero_for_empty_segments(): void
    {
        $this->assertEquals(0, ProcessCallJob::guessAgentSpeaker([]));
    }
}
