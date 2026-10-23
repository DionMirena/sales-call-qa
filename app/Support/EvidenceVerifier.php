<?php

namespace App\Support;

use Illuminate\Support\Collection;

class EvidenceVerifier
{
    /**
     * Verify evidence items against transcript segments.
     *
     * @param  array<array{segment_code: string, quote: string}>  $items
     * @param  Collection  $segments  ordered by start_ms
     * @return array{0: string, 1: float, 2: array}  [verification_status, confidence_multiplier, enriched_items]
     */
    public function verify(array $items, Collection $segments): array
    {
        if (empty($items)) {
            return ['unverified', 1.0, []];
        }

        $segsByCode = $segments->keyBy('code');
        $segList    = $segments->values();
        $enriched   = [];
        $statuses   = [];

        foreach ($items as $item) {
            $code  = $item['segment_code'] ?? '';
            $quote = $item['quote'] ?? '';

            if (! $segsByCode->has($code)) {
                $enriched[] = ['segment_code' => $code, 'quote' => $quote, 'verified' => false];
                $statuses[] = 'unverified';
                continue;
            }

            $seg       = $segsByCode->get($code);
            $idx       = $segList->search(fn ($s) => $s->code === $code);
            $normQuote = $this->normalise($quote);
            $normText  = $this->normalise($seg->text);

            // Step 1: exact substring
            if ($normQuote !== '' && str_contains($normText, $normQuote)) {
                $enriched[] = ['segment_code' => $code, 'quote' => $quote, 'verified' => true];
                $statuses[] = 'verified';
                continue;
            }

            // Step 2: cross-segment window (prev + current + next)
            $window = '';
            $prev   = $idx > 0 ? $segList->get($idx - 1) : null;
            $next   = $segList->get($idx + 1);
            if ($prev)  { $window .= $this->normalise($prev->text) . ' '; }
            $window .= $normText;
            if ($next)  { $window .= ' ' . $this->normalise($next->text); }

            if ($normQuote !== '' && str_contains($window, $normQuote)) {
                $enriched[] = ['segment_code' => $code, 'quote' => $quote, 'verified' => true];
                $statuses[] = 'verified';
                continue;
            }

            // Step 3: fuzzy token overlap
            $status     = $this->fuzzyStatus($normQuote, $normText);
            $enriched[] = ['segment_code' => $code, 'quote' => $quote, 'verified' => $status === 'verified'];
            $statuses[] = $status;
        }

        if (in_array('unverified', $statuses)) {
            $verificationStatus = 'unverified';
        } elseif (in_array('partial', $statuses)) {
            $verificationStatus = 'partial';
        } else {
            $verificationStatus = 'verified';
        }

        $multiplier = match ($verificationStatus) {
            'unverified' => 0.5,
            'partial'    => 0.75,
            default      => 1.0,
        };

        return [$verificationStatus, $multiplier, $enriched];
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace("/[^\p{L}\p{N}'\s]/u", ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function fuzzyStatus(string $normQuote, string $normText): string
    {
        $quoteTokens = array_values(array_filter(explode(' ', $normQuote)));
        $textSet     = array_flip(array_filter(explode(' ', $normText)));

        if (empty($quoteTokens)) {
            return 'unverified';
        }

        $matches = count(array_filter($quoteTokens, fn ($t) => isset($textSet[$t])));
        $ratio   = $matches / count($quoteTokens);

        $verifiedThreshold = (float) config('qa.scoring.fuzzy_verified_threshold', 0.85);
        $partialThreshold  = (float) config('qa.scoring.fuzzy_partial_threshold', 0.60);

        if ($ratio >= $verifiedThreshold) { return 'verified'; }
        if ($ratio >= $partialThreshold)  { return 'partial'; }

        return 'unverified';
    }
}
