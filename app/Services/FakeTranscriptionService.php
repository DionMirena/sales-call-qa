<?php

namespace App\Services;

class FakeTranscriptionService implements TranscriptionService
{
    public function transcribe(string $path): array
    {
        // Returns a realistic fixture — two speakers, 12 segments, ~3 minutes
        return [
            'duration_seconds' => 163,
            'segments'         => [
                ['speaker' => 0, 'start_ms' => 1000,   'end_ms' => 8000,   'text' => 'Hello, this is Sarah Mitchell from Insurvas Insurance. Just to let you know, this call may be recorded for training and quality purposes.'],
                ['speaker' => 1, 'start_ms' => 8500,   'end_ms' => 10000,  'text' => 'Sure, that\'s fine.'],
                ['speaker' => 0, 'start_ms' => 10500,  'end_ms' => 18000,  'text' => 'Wonderful. I\'m calling today because you recently submitted an enquiry about life insurance cover. Could you tell me your age?'],
                ['speaker' => 1, 'start_ms' => 18500,  'end_ms' => 20000,  'text' => 'I\'m thirty eight.'],
                ['speaker' => 0, 'start_ms' => 20500,  'end_ms' => 30000,  'text' => 'Thank you. Do you have any existing life insurance cover? And do you have children or other dependants?'],
                ['speaker' => 1, 'start_ms' => 30500,  'end_ms' => 38000,  'text' => 'No existing cover. I have two kids, seven and nine.'],
                ['speaker' => 0, 'start_ms' => 38500,  'end_ms' => 48000,  'text' => 'What kind of monthly premium are you comfortable with?'],
                ['speaker' => 1, 'start_ms' => 48500,  'end_ms' => 54000,  'text' => 'Somewhere between thirty and fifty pounds a month.'],
                ['speaker' => 0, 'start_ms' => 54500,  'end_ms' => 90000,  'text' => 'Based on what you\'ve told me, I\'d suggest a level term policy for five hundred thousand pounds of cover over a twenty five year term, around thirty two to thirty six pounds a month. The final premium is subject to underwriting and is not guaranteed.'],
                ['speaker' => 0, 'start_ms' => 90500,  'end_ms' => 108000, 'text' => 'Once the policy is issued, you have a thirty day cooling off period where you can cancel and receive a full refund.'],
                ['speaker' => 1, 'start_ms' => 108500, 'end_ms' => 112000, 'text' => 'Good to know. That sounds reasonable.'],
                ['speaker' => 0, 'start_ms' => 112500, 'end_ms' => 130000, 'text' => 'The next step is I\'ll send you a summary email with all the details and a link to start the application. The underwriting team will follow up within two working days.'],
                ['speaker' => 1, 'start_ms' => 130500, 'end_ms' => 135000, 'text' => 'Yes, perfect. Thank you Sarah.'],
                ['speaker' => 0, 'start_ms' => 135500, 'end_ms' => 140000, 'text' => 'Thank you for your time. Have a lovely day.'],
            ],
        ];
    }
}
