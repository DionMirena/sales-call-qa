<?php

namespace App\Services;

interface TranscriptionService
{
    /**
     * Transcribe the audio file at $path.
     *
     * @return array{segments: list<array{speaker:int, start_ms:int, end_ms:int, text:string}>, duration_seconds: int}
     */
    public function transcribe(string $path): array;
}
