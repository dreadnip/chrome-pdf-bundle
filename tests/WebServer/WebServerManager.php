<?php

declare(strict_types=1);

namespace Dreadnip\ChromePdfBundle\Test\WebServer;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class WebServerManager
{
    use WebServerReadinessTrait;

    private string $hostname;
    private int $port;
    private string $readinessPath;

    private Process $process;

    /**
     * @throws \RuntimeException
     */
    public function __construct(string $documentRoot, string $hostname = '127.0.0.1', int $port = 45066, string $router = '', string $readinessPath = '', array $env = null)
    {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->readinessPath = $readinessPath;

        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find(false)) {
            throw new \RuntimeException('Unable to find the PHP binary.');
        }

        if (isset($_SERVER['PANTHER_APP_ENV'])) {
            if (null === $env) {
                $env = [];
            }
            $env['APP_ENV'] = $_SERVER['PANTHER_APP_ENV'];
        }

        $this->process = new Process(
            array_filter(array_merge(
                [$binary],
                $finder->findArguments(),
                [
                    '-dvariables_order=EGPCS',
                    '-S',
                    sprintf('%s:%d', $this->hostname, $this->port),
                    '-t',
                    $documentRoot,
                    $router,
                ]
            )),
            $documentRoot,
            $env,
            null,
            null
        );
        $this->process->disableOutput();
    }

    public function start(): void
    {
        $this->checkPortAvailable($this->hostname, $this->port);
        $this->process->start();

        $url = "http://$this->hostname:$this->port";

        if ($this->readinessPath) {
            $url .= $this->readinessPath;
        }

        $this->waitUntilReady($this->process, $url, 'web server', true);
    }

    /**
     * @throws \RuntimeException
     */
    public function quit(): void
    {
        $this->process->stop();
    }

    public function isStarted(): bool
    {
        return $this->process->isStarted();
    }
}