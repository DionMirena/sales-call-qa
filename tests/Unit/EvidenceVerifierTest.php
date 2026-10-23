<?php

namespace Tests\Unit;

use App\Models\TranscriptSegment;
use App\Support\EvidenceVerifier;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EvidenceVerifierTest extends TestCase
{
    private EvidenceVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new EvidenceVerifier();
    }

    private function seg(string $code, string $text): TranscriptSegment
    {
        return TranscriptSegment::make(['code' => $code, 'text' => $text, 'speaker' => 0, 'start_ms' => 0, 'end_ms' => 0]);
    }

    private function segments(array $pairs): Collection
    {
        return collect(array_map(fn ($p) => $this->seg($p[0], $p[1]), $pairs));
    }

    // ── Empty evidence ────────────────────────────────────────────────────────

    public function test_empty_evidence_returns_unverified_with_no_penalty(): void
    {
        $segs = $this->segments([['S1', 'Hello world']]);
        [$status, $multiplier, $enriched] = $this->verifier->verify([], $segs);

        $this->assertEquals('unverified', $status);
        $this->assertEquals(1.0, $multiplier);
        $this->assertEmpty($enriched);
    }

    // ── Exact match ───────────────────────────────────────────────────────────

    public function test_exact_substring_match_is_verified(): void
    {
        $segs = $this->segments([['S1', 'Hello, this is Sarah Mitchell from Clearview Life Insurance.']]);
        [$status, $mult, $enriched] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'Sarah Mitchell from Clearview'],
        ], $segs);

        $this->assertEquals('verified', $status);
        $this->assertEquals(1.0, $mult);
        $this->assertTrue($enriched[0]['verified']);
    }

    public function test_exact_match_is_case_insensitive(): void
    {
        $segs = $this->segments([['S1', 'HELLO THIS IS SARAH']]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'hello this is sarah'],
        ], $segs);

        $this->assertEquals('verified', $status);
    }

    public function test_exact_match_ignores_punctuation(): void
    {
        $segs = $this->segments([['S1', 'This call may be recorded.']]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'This call may be recorded'],
        ], $segs);

        $this->assertEquals('verified', $status);
    }

    // ── Cross-segment window ──────────────────────────────────────────────────

    public function test_cross_segment_window_finds_quote_spanning_adjacent_segments(): void
    {
        $segs = $this->segments([
            ['S1', 'My name is Sarah'],
            ['S2', 'Mitchell from Clearview.'],
            ['S3', 'How can I help you?'],
        ]);

        // Quote spans S1 text and beginning of S2 text
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'Sarah Mitchell from'],
        ], $segs);

        $this->assertEquals('verified', $status);
    }

    // ── Fuzzy matching ────────────────────────────────────────────────────────

    public function test_fuzzy_high_overlap_is_verified(): void
    {
        // 8/9 tokens match → ~0.89 ratio → verified
        $segs = $this->segments([['S1', 'The premium is thirty four pounds per month for this policy']]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'The premium is thirty four pounds per month for'],
        ], $segs);

        $this->assertEquals('verified', $status);
    }

    public function test_fuzzy_medium_overlap_is_partial(): void
    {
        // Quote: 5 tokens; text has 3 matching → 0.60 ratio → partial
        $segs = $this->segments([['S1', 'Yes the premium thirty something monthly']]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'Yes the premium policy rate'],
        ], $segs);

        $this->assertEquals('partial', $status);
    }

    public function test_fuzzy_low_overlap_is_unverified(): void
    {
        // Completely different words
        $segs = $this->segments([['S1', 'Good morning how are you']]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'Policy payment overdue immediately'],
        ], $segs);

        $this->assertEquals('unverified', $status);
    }

    // ── Unknown segment code ──────────────────────────────────────────────────

    public function test_nonexistent_segment_code_is_unverified(): void
    {
        $segs = $this->segments([['S1', 'Hello world']]);
        [$status, $mult, $enriched] = $this->verifier->verify([
            ['segment_code' => 'S99', 'quote' => 'Hello world'],
        ], $segs);

        $this->assertEquals('unverified', $status);
        $this->assertEquals(0.5, $mult);
        $this->assertFalse($enriched[0]['verified']);
    }

    // ── Per-criterion verdict from mixed items ────────────────────────────────

    public function test_any_unverified_item_gives_unverified_status(): void
    {
        $segs = $this->segments([['S1', 'Hello I am Sarah']]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1',  'quote' => 'Hello I am Sarah'],   // verified
            ['segment_code' => 'S99', 'quote' => 'Not in transcript'],  // unverified
        ], $segs);

        $this->assertEquals('unverified', $status);
    }

    public function test_all_verified_items_give_verified_status(): void
    {
        $segs = $this->segments([
            ['S1', 'Hello I am Sarah'],
            ['S2', 'From Clearview Insurance'],
        ]);
        [$status] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'Hello I am Sarah'],
            ['segment_code' => 'S2', 'quote' => 'From Clearview Insurance'],
        ], $segs);

        $this->assertEquals('verified', $status);
    }

    // ── Confidence multipliers ────────────────────────────────────────────────

    public function test_unverified_status_halves_confidence(): void
    {
        $segs = $this->segments([['S1', 'irrelevant text']]);
        [, $mult] = $this->verifier->verify([
            ['segment_code' => 'S99', 'quote' => 'not here'],
        ], $segs);

        $this->assertEquals(0.5, $mult);
    }

    public function test_partial_status_reduces_confidence_to_75_percent(): void
    {
        $segs = $this->segments([['S1', 'Yes the premium thirty something monthly']]);
        [, $mult] = $this->verifier->verify([
            ['segment_code' => 'S1', 'quote' => 'Yes the premium policy rate'],
        ], $segs);

        $this->assertEquals(0.75, $mult);
    }
}
