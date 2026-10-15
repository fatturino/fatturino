<?php

namespace App\Services;

use App\Contracts\SdiSubmissionDriver;
use InvalidArgumentException;

class SdiSubmissionDriverRegistry
{
    /** @var array<string, SdiSubmissionDriver> */
    private array $drivers;

    /** @param iterable<SdiSubmissionDriver> $drivers */
    public function __construct(iterable $drivers)
    {
        foreach ($drivers as $driver) {
            $this->drivers[$driver->key()] = $driver;
        }
    }

    public function get(string $key): SdiSubmissionDriver
    {
        return $this->drivers[$key]
            ?? throw new InvalidArgumentException("SDI submission driver [{$key}] is not registered.");
    }
}
