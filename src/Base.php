<?php

namespace Eduplus;

class Base {
    /**
     * Execute a terminal command using proc_open (exec() alternative)
     *
     * @param string|array $command   Command string or array (recommended: array)
     * @param array|null   $output    Captured output lines (like exec)
     * @param int|null     $exitCode  Exit status code
     * @param string|null  $cwd       Working directory
     * @param array|null   $env       Environment variables
     *
     * @return string Last line of output (same as exec)
     */
    public function terminal($command, &$output = null, &$exitCode = null, $cwd = null, $env = null)
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('proc_open() is disabled');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'], // STDIN
            1 => ['pipe', 'w'], // STDOUT
            2 => ['pipe', 'w'], // STDERR
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start process');
        }

        fclose($pipes[0]); // No STDIN needed

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        // Normalize output like exec()
        $lines = preg_split("/\r\n|\n|\r/", trim($stdout));
        $output = $lines ?: [];

        // Append stderr if command fails
        if ($exitCode !== 0 && $stderr) {
            $output[] = trim($stderr);
        }

        return end($output);
    }
}