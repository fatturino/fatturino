<?php

namespace App\Sdi;

readonly class SdiSubmissionResult
{
    private function __construct(
        public bool $accepted,
        public bool $definitivelyRejected,
        public ?string $remoteReference,
        public ?string $remoteFileId,
        public ?string $message,
        public ?string $errorCode,
        public ?int $httpStatus,
    ) {}

    public static function accepted(string $remoteReference, ?string $remoteFileId, ?string $message): self
    {
        $remoteReference = trim($remoteReference);

        if ($remoteReference === '') {
            throw new \InvalidArgumentException('An accepted SDI submission requires a remote reference.');
        }

        return new self(true, false, $remoteReference, $remoteFileId, $message, null, null);
    }

    public static function rejected(?string $message, ?string $errorCode, ?int $httpStatus): self
    {
        return new self(false, true, null, null, $message, $errorCode, $httpStatus);
    }

    public static function unknown(?string $message, ?int $httpStatus = null): self
    {
        return new self(false, false, null, null, $message, null, $httpStatus);
    }

    public function outcomeIsUnknown(): bool
    {
        return ! $this->accepted && ! $this->definitivelyRejected;
    }
}
