<?php

namespace Eduplus\Qr\Exceptions;

/**
 * The binary ran but could not generate the code. The message is the
 * binary's own error text (e.g. "ean13: invalid check digit: got 8, expected 7").
 */
class GenerationException extends EduplusException
{
    /** Exit codes of the EduplusQR binary. */
    public const INVALID_INPUT = 1;
    public const IO_ERROR = 2;
    public const VERIFY_FAILED = 3;
    public const BATCH_PARTIAL = 4;
    public const TIMEOUT = 124;

    /** @var int */
    private $exitCode;

    /** @var string */
    private $stderr;

    public function __construct(string $message, int $exitCode = 0, string $stderr = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, $exitCode, $previous);
        $this->exitCode = $exitCode;
        $this->stderr = $stderr;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    /** The input could not be encoded (wrong length, bad check digit, too long, ...). */
    public function isInvalidInput(): bool
    {
        return $this->exitCode === self::INVALID_INPUT;
    }

    /** A file could not be read or written. */
    public function isIoError(): bool
    {
        return $this->exitCode === self::IO_ERROR;
    }

    /** The generated image did not decode back to the input. */
    public function isVerifyFailure(): bool
    {
        return $this->exitCode === self::VERIFY_FAILED;
    }

    public function isTimeout(): bool
    {
        return $this->exitCode === self::TIMEOUT;
    }
}
