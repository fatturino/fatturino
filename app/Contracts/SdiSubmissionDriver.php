<?php

namespace App\Contracts;

use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;

interface SdiSubmissionDriver
{
    public function key(): string;

    public function submit(SdiSubmissionCommand $command): SdiSubmissionResult;
}
