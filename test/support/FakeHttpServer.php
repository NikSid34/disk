<?php

declare(strict_types=1);

namespace app\test\support;

use RuntimeException;

class FakeHttpServer {
    /** @var resource|null */
    private $process;
    private string $stdoutPath;
    private string $stderrPath;

    private function __construct($process, string $stdoutPath, string $stderrPath) {
        $this->process = $process;
        $this->stdoutPath = $stdoutPath;
        $this->stderrPath = $stderrPath;
    }

    public static function start(int $port, string $routerFile, array $environment = []): self {
        $stdoutPath = tempnam(sys_get_temp_dir(), 'simpledisk-http-out-');
        $stderrPath = tempnam(sys_get_temp_dir(), 'simpledisk-http-err-');

        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, $routerFile];
        $process = proc_open(
                $command,
                [
                        0 => ['pipe', 'r'],
                        1 => ['file', $stdoutPath, 'a'],
                        2 => ['file', $stderrPath, 'a'],
                ],
                $pipes,
                dirname($routerFile),
                array_merge($_ENV, $environment)
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start local test server');
        }

        fclose($pipes[0]);

        $server = new self($process, $stdoutPath, $stderrPath);
        $server->waitUntilReady($port);

        return $server;
    }

    public function stop(): void {
        if (!is_resource($this->process)) {
            return;
        }

        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;

        if (is_file($this->stdoutPath)) {
            unlink($this->stdoutPath);
        }

        if (is_file($this->stderrPath)) {
            unlink($this->stderrPath);
        }
    }

    public function __destruct() {
        $this->stop();
    }

    public static function findFreePort(): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException('Failed to allocate free port: ' . $errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if (!is_string($name) || !str_contains($name, ':')) {
            throw new RuntimeException('Failed to determine free port');
        }

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitUntilReady(int $port): void {
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $connection = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.2);
            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(100000);
        }

        $errorOutput = is_file($this->stderrPath) ? file_get_contents($this->stderrPath) : '';
        throw new RuntimeException('Local test server did not start: ' . $errorOutput);
    }
}
