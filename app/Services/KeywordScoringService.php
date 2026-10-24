<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Criterion;
use App\Support\ScoreCalculator;
use Illuminate\Support\Collection;

/**
 * Free, offline scorer used when no Anthropic API key is configured.
 *
 * It scores the real transcript with keyword matching: words from each
 * criterion's name and description are looked up in the agent's lines, and
 * the best-matching lines are stored as (genuine) evidence. Prohibited-phrase
 * criteria ("Agent never…") fail only when a red-flag phrase is actually said.
 * Uncertain results are flagged for human review.
 */
class KeywordScoringService implements ScoringService
{
    private const STOPWORDS = [
        'the', 'and', 'for', 'that', 'this', 'with', 'are', 'any', 'was', 'were', 'what', 'when', 'into',
        'their', 'they', 'them', 'there', 'from', 'about', 'before', 'after', 'during', 'within', 'being',
        'agent', 'customer', 'customers', 'clearly', 'states', 'state', 'asks', 'call', 'calls', 'does',
        'should', 'must', 'which', 'whether', 'how', 'many', 'much', 'other', 'such', 'also', 'has', 'have',
        'not', 'never', 'every', 'each', 'can', 'may', 'will', 'would', 'could', 'its', 'his', 'her', 'e.g',
        'relevant', 'significant', 'basic', 'first', 'seconds', 'opening', 'discovery', 'compliance', 'close',
        'product', 'explanation', 'needs', 'information', 'given', 'exact', 'record', 'figure', 'only',
    ];

    private const GUARANTEE_PHRASES = [
        'guaranteed approval', 'guaranteed to be approved', 'definitely covered', 'definitely be covered',
        'definitely approved', 'no medical questions', 'everyone gets approved', 'guaranteed acceptance',
        'cover is guaranteed', 'guarantee you',
    ];

    private const PRESSURE_PHRASES = [
        'today only', 'only today', 'right now', 'last chance', 'act now', 'act fast', 'expires today',
        'offer expires', "won't last", 'too late', 'decide now', 'decide today', 'limited time',
        'need an answer now', 'sign up now', 'before the end of the call', 'what are you waiting for',
        "won't be available indefinitely", 'lock in the', 'secure the rate', 'worth acting on',
        'pricing window', 'rates go up', 'rates going up',
    ];

    public function score(Call $call): void
    {
        $call->load(['template.criteria', 'segments']);

        $segments     = $call->segments->sortBy('start_ms')->values();
        $agentSpeaker = $call->agent_speaker ?? 0;
        $agentLines   = $segments->where('speaker', $agentSpeaker)->values();

        foreach ($call->template->criteria->sortBy('position') as $criterion) {
            $outcome = match (true) {
                $criterion->type === 'extract'   => $this->extract($agentLines),
                $criterion->type === 'scale_1_5' => $this->scaleTone($agentLines),
                $this->isProhibition($criterion) => $this->checkProhibition($criterion, $agentLines),
                default                          => $this->checkRequirement($criterion, $agentLines, $segments),
            };

            $call->criterionResults()->create([
                'criterion_id'          => $criterion->id,
                'criterion_name'        => $criterion->name,
                'criterion_description' => $criterion->description,
                'criterion_type'        => $criterion->type,
                'criterion_weight'      => $criterion->weight,
                'criterion_is_critical' => $criterion->is_critical,
                'ai_result'             => $outcome['result'],
                'ai_score'              => $outcome['score'],
                'confidence'            => $outcome['confidence'],
                'reasoning'             => $outcome['reasoning'],
                'evidence'              => $outcome['evidence'],
                'verification_status'   => $outcome['evidence'] ? 'verified' : 'unverified',
                'needs_review'          => $outcome['confidence'] < (float) config('qa.scoring.needs_review_confidence_threshold', 0.6)
                    || ($criterion->is_critical && in_array($outcome['result'], ['fail', 'no'], true)),
            ]);
        }

        [$scorePercent, $outcome] = ScoreCalculator::calculate($call->template, $call->criterionResults()->get());

        $call->update([
            'score_percent' => $scorePercent,
            'outcome'       => $outcome,
            'status'        => 'done',
        ]);
    }

    /** Something the agent must do: pass when the agent says matching words. */
    private function checkRequirement(Criterion $criterion, Collection $agentLines, Collection $segments): array
    {
        $lines    = $criterion->phase_hint === 'opening' ? $this->openingWindow($agentLines, $segments) : $agentLines;
        $concepts = $this->conceptsFor($criterion->name);

        if ($concepts) {
            return $this->checkConcepts($criterion, $concepts, $lines);
        }

        $keywords = $this->keywords($criterion->name . ' ' . $criterion->description);

        $matches = $lines
            ->map(fn ($seg) => ['seg' => $seg, 'hits' => array_values(array_intersect($keywords, $this->stems($seg->text)))])
            ->filter(fn ($m) => count($m['hits']) >= 2)
            ->sortByDesc(fn ($m) => count($m['hits']))
            ->take(2)
            ->values();

        $passed = $matches->isNotEmpty();
        $best   = $passed ? count($matches[0]['hits']) : 0;
        $hits   = $matches->flatMap(fn ($m) => $m['hits'])->unique()->values()->all();

        return [
            'result'     => $this->positive($criterion->type, $passed),
            'score'      => $passed ? 1.0 : 0.0,
            'confidence' => $passed ? min(0.9, 0.55 + 0.08 * $best) : 0.5,
            'reasoning'  => $passed
                ? 'Agent said matching words (' . implode(', ', $hits) . '). Rule-based check, no AI.'
                : 'No agent line matched this criterion' . ($criterion->phase_hint === 'opening' ? ' in the opening' : '') . '. Rule-based check, no AI — please review.',
            'evidence'   => $matches->map(fn ($m) => $this->evidence($m['seg']))->all(),
        ];
    }

    /**
     * Common sales-call checks, recognised by words in the criterion name and
     * matched with phrasings agents actually use. Every concept must be found.
     */
    private const CONCEPTS = [
        'identification' => ['label' => 'agent introduction',  'pattern' => '/\b((?i:my name is)|(?i:this is) [A-Z][a-z]+|(?i:i\'?m) [A-Z][a-z]+,? (?i:from|with|at|calling))/'],
        'recording'      => ['label' => 'recording notice',    'pattern' => '/\brecord(ed|ing)\b/i'],
        'purpose'        => ['label' => 'reason for the call', 'pattern' => '/\b(calling (you )?(today )?(because|about|regarding|to)|reason for (my|the|this) call|following up|follow up on|your (recent )?(enquiry|inquiry|request|application)|transferred\b.{0,20}\b(about|regarding))/i'],
        'age'            => ['label' => 'age question',        'pattern' => '/\b(how old|your age|age is|date of birth|what age)\b/i'],
        'health'         => ['label' => 'health question',     'pattern' => '/\b(health|medical|smoke|smoker|conditions?|illness)\b/i'],
        'existing cover' => ['label' => 'existing cover',      'pattern' => '/(\b(existing|already have|current(ly)?)\b.{0,30}\b(cover|policy|insurance)\b|\b(cover|policy|insurance)\b.{0,15}\bcurrently\b)/i'],
        'dependant'      => ['label' => 'dependants',          'pattern' => '/\b(dependants?|dependents?|children|kids|rely on)\b/i'],
        'budget'         => ['label' => 'budget question',     'pattern' => '/\b(budget|afford|comfortable|spend)\b/i'],
        'amount'         => ['label' => 'cover amount',        'pattern' => '/([£$€]\s?\d|\b\d{1,3}(,\d{3})+\b|\b\d[\d,]*\s?(k|thousand|million)\b|\b(thousand|million) (pounds|dollars|euros)|sum assured|of cover)/iu'],
        'term'           => ['label' => 'policy term',         'pattern' => '/(\b(\d+|five|ten|twelve|fifteen|twenty|twenty[- ]five|thirty|thirty[- ]five|forty)[- ]years?\b|\byear term\b|whole[- ](of[- ])?life)/i'],
        'cooling'        => ['label' => 'cooling-off period',  'pattern' => '/\b(cooling[- ]off|cancel\w*.{0,40}refund)/i'],
        'next step'      => ['label' => 'next steps',          'pattern' => '/\b(next steps?|what happens next|i\'?ll (send|email|get|have)\b|we\'?ll send|you\'?ll (receive|get)|will follow up|within \w+ (working )?days|send (that|it) over|get that sent)\b/i'],
    ];

    private function conceptsFor(string $name): array
    {
        $name = mb_strtolower($name);

        return array_values(array_filter(
            array_keys(self::CONCEPTS),
            fn ($key) => preg_match('/\b' . preg_quote($key, '/') . 's?\b/', $name)
        ));
    }

    private function checkConcepts(Criterion $criterion, array $concepts, Collection $lines): array
    {
        $found = [];
        foreach ($concepts as $key) {
            $found[$key] = $lines->first(fn ($seg) => preg_match(self::CONCEPTS[$key]['pattern'], $seg->text));
        }

        $missing = array_keys(array_filter($found, fn ($seg) => $seg === null));
        $hits    = array_filter($found);
        $passed  = $missing === [];
        $labels  = fn (array $keys) => implode(', ', array_map(fn ($k) => self::CONCEPTS[$k]['label'], $keys));

        return [
            'result'     => $this->positive($criterion->type, $passed),
            'score'      => $passed ? 1.0 : 0.0,
            'confidence' => $passed ? 0.85 : 0.55,
            'reasoning'  => $passed
                ? 'Agent gave the ' . $labels(array_keys($hits)) . '. Rule-based check, no AI.'
                : 'Could not find the ' . $labels($missing) . ($criterion->phase_hint === 'opening' ? ' in the opening' : '') . '. Rule-based check, no AI — please review.',
            'evidence'   => collect($hits)->unique('code')->map(fn ($seg) => $this->evidence($seg))->values()->all(),
        ];
    }

    /** Something the agent must never do: fail only when a red-flag phrase is said. */
    private function checkProhibition(Criterion $criterion, Collection $agentLines): array
    {
        $phrases = $this->redFlagPhrases($criterion);

        $flagged = $agentLines->map(fn ($seg) => ['seg' => $seg, 'phrase' => $this->findPhrase($seg->text, $phrases)])
            ->filter(fn ($m) => $m['phrase'] !== null)
            ->take(2)
            ->values();

        $failed = $flagged->isNotEmpty();

        return [
            'result'     => $this->positive($criterion->type, ! $failed),
            'score'      => $failed ? 0.0 : 1.0,
            'confidence' => $failed ? 0.8 : 0.7,
            'reasoning'  => $failed
                ? 'Agent said "' . $flagged[0]['phrase'] . '". Rule-based check, no AI.'
                : 'None of the prohibited phrases were said. Rule-based check, no AI.',
            'evidence'   => $flagged->map(fn ($m) => $this->evidence($m['seg']))->all(),
        ];
    }

    /** Tone cannot be judged from keywords, so start at "good" and always ask for review. */
    private function scaleTone(Collection $agentLines): array
    {
        $pressure = $agentLines->first(fn ($seg) => $this->findPhrase($seg->text, self::PRESSURE_PHRASES) !== null);

        return [
            'result'     => $pressure ? '2' : '4',
            'score'      => $pressure ? 0.25 : 0.75,
            'confidence' => 0.5,
            'reasoning'  => $pressure
                ? 'Pressure language detected, so tone is marked down. Rule-based estimate — please review.'
                : 'No pressure language detected. Tone is a rule-based estimate — please review.',
            'evidence'   => $pressure ? [$this->evidence($pressure)] : [],
        ];
    }

    /** Pull the first money amount the agent mentions, preferring one said "per month". */
    private function extract(Collection $agentLines): array
    {
        $amount = '(?:[£$€]\s?\d[\d,]*(?:\.\d{1,2})?|\d[\d,]*(?:\.\d{1,2})?\s?(?:pounds|dollars|euros))';
        $money  = '/(?:\d[\d,]*\s?(?:to|-)\s?)?' . $amount . '(?:\s*(?:a|per)\s*month)?/iu';
        $found  = null;

        foreach ($agentLines as $i => $seg) {
            // A quote can be split across lines ("around 32 to £36" / "a month"), so read on into the next line.
            $text = $seg->text . ' ' . mb_substr($agentLines[$i + 1]->text ?? '', 0, 20);

            if (preg_match_all($money, $text, $all)) {
                $monthly = collect($all[0])->first(fn ($m) => stripos($m, 'month') !== false);
                if ($monthly) {
                    $found = [$seg, trim($monthly)];
                    break;
                }
                $found ??= [$seg, trim($all[0][0])];
            }
        }

        return [
            'result'     => $found ? $found[1] : '',
            'score'      => null,
            'confidence' => $found ? 0.7 : 0.5,
            'reasoning'  => $found ? 'First amount the agent quoted. Rule-based extraction, no AI.' : 'No amount found in the agent\'s lines.',
            'evidence'   => $found ? [$this->evidence($found[0])] : [],
        ];
    }

    private function isProhibition(Criterion $criterion): bool
    {
        return (bool) preg_match('/^\s*agent\s+(never|does not|doesn\'t|must not|should not)\b/i', $criterion->description);
    }

    private function redFlagPhrases(Criterion $criterion): array
    {
        $text = mb_strtolower($criterion->name . ' ' . $criterion->description . ' ' . $criterion->fail_conditions);

        preg_match_all('/["“]([^"”]{3,60})["”]/u', (string) $criterion->fail_conditions, $quoted);
        $phrases = array_map('mb_strtolower', $quoted[1]);

        if (str_contains($text, 'guarantee')) {
            $phrases = array_merge($phrases, self::GUARANTEE_PHRASES);
        }
        if (preg_match('/pressure|urgency|fear/', $text)) {
            $phrases = array_merge($phrases, self::PRESSURE_PHRASES);
        }

        return array_values(array_unique($phrases));
    }

    /** Returns the phrase found in the text, ignoring negated uses like "is not guaranteed". */
    private function findPhrase(string $text, array $phrases): ?string
    {
        $lower = mb_strtolower($text);

        foreach ($phrases as $phrase) {
            $pos = mb_strpos($lower, $phrase);
            if ($pos === false) {
                continue;
            }
            // "not guaranteed", "no rush to decide today", "you don't have to decide now"
            $before = mb_substr($lower, max(0, $pos - 30), min($pos, 30));
            if (! preg_match('/\b(not|no|never|isn\'t|aren\'t|can\'t|cannot|won\'t|don\'t|doesn\'t)\s+(\w+\s+){0,3}$/', $before)) {
                return $phrase;
            }
        }

        return null;
    }

    /** Agent lines in the first 30% of the call (at least the first 3 lines). */
    private function openingWindow(Collection $agentLines, Collection $segments): Collection
    {
        $cutoff = max(
            (int) (($segments->last()->end_ms ?? 0) * 0.3),
            (int) ($agentLines->take(3)->last()->start_ms ?? 0)
        );

        return $agentLines->filter(fn ($seg) => $seg->start_ms <= $cutoff)->values();
    }

    private function keywords(string $text): array
    {
        return array_values(array_unique(array_filter(
            $this->stems($text),
            fn ($stem) => ! in_array($stem, array_map(fn ($w) => $this->stem($w), self::STOPWORDS), true)
        )));
    }

    private function stems(string $text): array
    {
        preg_match_all('/[a-z]{3,}/', mb_strtolower(str_replace(['-', '–'], ' ', $text)), $words);

        return array_values(array_unique(array_map(fn ($w) => $this->stem($w), $words[0])));
    }

    /** Crude stemmer: the first five letters are enough to match record/recorded/recording. */
    private function stem(string $word): string
    {
        return mb_substr($word, 0, 5);
    }

    private function positive(string $type, bool $ok): string
    {
        return $type === 'yes_no_na' ? ($ok ? 'yes' : 'no') : ($ok ? 'pass' : 'fail');
    }

    private function evidence($segment): array
    {
        return [
            'segment_code' => $segment->code,
            'quote'        => mb_substr($segment->text, 0, 160),
            'verified'     => true,
        ];
    }
}
