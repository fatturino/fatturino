<?php

namespace App\Sdi;

readonly class SdiSubmissionCommand
{
    public function __construct(
        public string $xml,
        public int $submissionId,
        public string $correlationId,
        public string $fileName,
    ) {}
}
