<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AudioNormaliser
{
    public function normalise(string $inputPath, string $outputPath): void
    {
        $ffmpeg = config('qa.audio.ffmpeg_path', 'ffmpeg');

        $process = new Process([
            $ffmpeg,
            '-y',                    // overwrite output
            '-i', $inputPath,
            '-ac', '1',              // mono
            '-ar', '16000',          // 16 kHz
            '-b:a', '64k',
            '-f', 'mp3',
            $outputPath,
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'ffmpeg normalisation failed: ' . $process->getErrorOutput()
            );
        }

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('ffmpeg produced an empty output file.');
        }
    }
}
