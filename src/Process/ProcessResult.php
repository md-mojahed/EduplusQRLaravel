<?php

namespace Eduplus\Qr\Process;

/**
 * Output of one binary run.
 */
final class ProcessResult
{
    /** @var int */
    public $exitCode;

    /** @var string */
    public $stdout;

    /** @var string */
    public $stderr;

    /** @var bool */
    public $timedOut;

    public function __construct(int $exitCode, string $stdout, string $stderr, bool $timedOut = false)
    {
        $this->exitCode = $exitCode;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->timedOut = $timedOut;
    }

    public function successful(): bool
    {
        return $this->exitCode === 0 && !$this->timedOut;
    }
}
