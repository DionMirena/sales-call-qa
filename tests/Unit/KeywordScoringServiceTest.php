<?php

namespace Tests\Unit;

use App\Services\KeywordScoringService;
use ReflectionClass;
use Tests\TestCase;

class KeywordScoringServiceTest extends TestCase
{
    private KeywordScoringService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new KeywordScoringService();
    }

    private function concepts(string $criterionName): array
    {
        $ref = new ReflectionClass($this->svc);
        $m   = $ref->getMethod('conceptsFor');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $criterionName);
    }

    private function findPhrase(string $text, array $phrases): ?string
    {
        $ref = new ReflectionClass($this->svc);
        $m   = $ref->getMethod('findPhrase');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $text, $phrases);
    }

    private function pressurePhrases(): array
    {
        $c = new \ReflectionClassConstant(KeywordScoringService::class, 'PRESSURE_PHRASES');
        return $c->getValue();
    }

    // --- conceptsFor: word-boundary fix ---

    /** 'age' must not match inside 'agent'. */
    public function test_age_concept_does_not_match_agent_in_identification_criterion(): void
    {
        $concepts = $this->concepts('Opening – Agent Identification');
        $this->assertNotContains('age', $concepts);
        $this->assertContains('identification', $concepts);
    }

    public function test_age_concept_matches_standalone_age_word(): void
    {
        $concepts = $this->concepts('Needs Discovery – Age and Health Status');
        $this->assertContains('age', $concepts);
        $this->assertContains('health', $concepts);
    }

    public function test_next_step_concept_matches_plural_form(): void
    {
        $concepts = $this->concepts('Close – Clear Next Steps');
        $this->assertContains('next step', $concepts);
    }

    public function test_cooling_concept_matches_hyphenated_cooling_off(): void
    {
        $concepts = $this->concepts('Compliance – Cancellation / Cooling-Off Period');
        $this->assertContains('cooling', $concepts);
    }

    // --- next step pattern: catches common agent phrasings ---

    public function test_next_step_pattern_matches_ill_email(): void
    {
        $concepts = $this->constants('CONCEPTS');
        $pattern  = $concepts['next step']['pattern'];
        $this->assertMatchesRegularExpression($pattern, "I'll email you the summary today.");
    }

    public function test_next_step_pattern_matches_ill_get_that_sent(): void
    {
        $concepts = $this->constants('CONCEPTS');
        $pattern  = $concepts['next step']['pattern'];
        $this->assertMatchesRegularExpression($pattern, "Thank you. I'll get that sent right away.");
    }

    public function test_next_step_pattern_matches_ill_send(): void
    {
        $concepts = $this->constants('CONCEPTS');
        $pattern  = $concepts['next step']['pattern'];
        $this->assertMatchesRegularExpression($pattern, "I'll send you the illustration today.");
    }

    // --- US live-transfer phrasings ---

    public function test_purpose_pattern_matches_live_transfer_opening(): void
    {
        $pattern = $this->constants('CONCEPTS')['purpose']['pattern'];
        $this->assertMatchesRegularExpression($pattern, 'You just got transferred over to me about the final expense coverage.');
        $this->assertMatchesRegularExpression($pattern, "You've been transferred to me regarding your life insurance quote.");
    }

    public function test_purpose_pattern_ignores_unrelated_transfers(): void
    {
        $pattern = $this->constants('CONCEPTS')['purpose']['pattern'];
        $this->assertDoesNotMatchRegularExpression($pattern, 'My salary is transferred on Fridays.');
    }

    public function test_term_pattern_matches_us_and_uk_whole_life(): void
    {
        $pattern = $this->constants('CONCEPTS')['term']['pattern'];
        $this->assertMatchesRegularExpression($pattern, 'a whole life final expense policy');
        $this->assertMatchesRegularExpression($pattern, 'a whole-of-life policy');
        $this->assertMatchesRegularExpression($pattern, 'a twenty-year term life policy');
    }

    // --- pressure phrase detection ---

    public function test_findPhrase_detects_lock_in_the(): void
    {
        $phrases = $this->pressurePhrases();
        $result  = $this->findPhrase("If we apply this week you'd lock in the lower price.", $phrases);
        $this->assertNotNull($result);
        $this->assertStringContainsString('lock in the', $result);
    }

    public function test_findPhrase_detects_worth_acting_on(): void
    {
        $phrases = $this->pressurePhrases();
        $result  = $this->findPhrase("This enhanced rate is worth acting on while it's there.", $phrases);
        $this->assertNotNull($result);
    }

    public function test_findPhrase_does_not_flag_normal_next_steps_sentence(): void
    {
        $phrases = $this->pressurePhrases();
        $result  = $this->findPhrase("I'll email you the summary today.", $phrases);
        $this->assertNull($result);
    }

    public function test_findPhrase_does_not_flag_negated_pressure(): void
    {
        $phrases = $this->pressurePhrases();
        $result  = $this->findPhrase("There's no need to decide today.", $phrases);
        $this->assertNull($result);
    }

    // helper: read private constant from the class
    private function constants(string $constName): mixed
    {
        $ref = new ReflectionClass(KeywordScoringService::class);
        $c   = $ref->getReflectionConstant($constName);
        return $c ? $c->getValue() : null;
    }
}
