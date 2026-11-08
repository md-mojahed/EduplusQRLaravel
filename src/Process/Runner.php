<?php

namespace Eduplus\Qr\Process;

use Eduplus\Qr\Exceptions\EduplusException;

/**
 * Runs the binary with proc_open() using an argument array, so no shell is
 * involved and user text can never be interpreted as a command. stdin is
 * written while stdout/stderr are read (non-blocking), so large batches
 * cannot deadlock on full pipe buffers.
 */
class Runner
{
    /** @var float seconds; 0 = no limit */
    private $timeout;

    public function __construct(float $timeout = 120.0)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param string[] $command binary followed by its arguments
     */
    public function run(array $command, string $stdin = ''): ProcessResult
    {
        if (!function_exists('proc_open')) {
            throw new EduplusException('proc_open() is disabled in php.ini (disable_functions); EduplusQR needs it to run the binary.');
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->runWindows($command, $stdin);
        }

        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open(array_map('strval', $command), $spec, $pipes);
        if (!is_resource($process)) {
            $err = error_get_last();
            throw new EduplusException('Unable to start ' . $command[0] . ($err ? ': ' . $err['message'] : ''));
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $written = 0;
        $inputLength = strlen($stdin);
        if ($inputLength === 0) {
            fclose($pipes[0]);
            $pipes[0] = null;
        } else {
            stream_set_blocking($pipes[0], false);
        }

        $start = microtime(true);
        $timedOut = false;
        while (true) {
            $read = [];
            foreach ([1, 2] as $i) {
                if ($pipes[$i] !== null) {
                    $read[] = $pipes[$i];
                }
            }
            $write = $pipes[0] !== null ? [$pipes[0]] : [];
            if (!$read && !$write) {
                break;
            }
            $except = null;
            $changed = @stream_select($read, $write, $except, 0, 200000);
            if ($changed === false) {
                break; // interrupted; fall through to collect what we have
            }

            foreach ($write as $w) {
                $chunk = substr($stdin, $written, 65536);
                $n = @fwrite($w, $chunk);
                if ($n === false || $n === 0 && feof($w)) {
                    $written = $inputLength; // child closed stdin
                } else {
                    $written += $n;
                }
                if ($written >= $inputLength) {
                    fclose($pipes[0]);
                    $pipes[0] = null;
                }
            }
            foreach ($read as $r) {
                $data = fread($r, 65536);
                $idx = $r === $pipes[1] ? 1 : 2;
                if ($data !== false && $data !== '') {
                    if ($idx === 1) {
                        $stdout .= $data;
                    } else {
                        $stderr .= $data;
                    }
                }
                if (feof($r)) {
                    fclose($r);
                    $pipes[$idx] = null;
                }
            }

            if ($this->timeout > 0 && microtime(true) - $start > $this->timeout) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }
        }

        foreach ($pipes as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
        $status = proc_close($process);

        if ($timedOut) {
            return new ProcessResult(124, $stdout, $stderr, true);
        }
        return new ProcessResult($status, $stdout, $stderr);
    }

    /**
     * Windows cannot select() on process pipes, so output goes to temporary
     * files and stdin is written with a blocking pipe (the child consumes it
     * while writing its output to the files, so nothing can deadlock).
     *
     * @param string[] $command
     */
    private function runWindows(array $command, string $stdin): ProcessResult
    {
        $outFile = tempnam(sys_get_temp_dir(), 'eqo');
        $errFile = tempnam(sys_get_temp_dir(), 'eqe');
        if ($outFile === false || $errFile === false) {
            throw new EduplusException('Unable to create temporary files in ' . sys_get_temp_dir());
        }
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['file', $outFile, 'w'],
            2 => ['file', $errFile, 'w'],
        ];
        try {
            $process = @proc_open(array_map('strval', $command), $spec, $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($process)) {
                $err = error_get_last();
                throw new EduplusException('Unable to start ' . $command[0] . ($err ? ': ' . $err['message'] : ''));
            }
            if ($stdin !== '') {
                $len = strlen($stdin);
                for ($written = 0; $written < $len;) {
                    $n = @fwrite($pipes[0], substr($stdin, $written, 65536));
                    if ($n === false || $n === 0) {
                        break;
                    }
                    $written += $n;
                }
            }
            fclose($pipes[0]);

            $start = microtime(true);
            $timedOut = false;
            $exitCode = -1;
            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exitCode = (int) $status['exitcode'];
                    break;
                }
                if ($this->timeout > 0 && microtime(true) - $start > $this->timeout) {
                    $timedOut = true;
                    proc_terminate($process);
                    break;
                }
                usleep(10000);
            }
            $closed = proc_close($process);
            if ($exitCode === -1) {
                $exitCode = $closed;
            }
            $stdout = (string) file_get_contents($outFile);
            $stderr = (string) file_get_contents($errFile);
            return new ProcessResult($timedOut ? 124 : $exitCode, $stdout, $stderr, $timedOut);
        } finally {
            @unlink($outFile);
            @unlink($errFile);
        }
    }
}
