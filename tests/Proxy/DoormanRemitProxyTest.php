<?php

namespace AsyncPHP\Assistant\Tests\Proxy;

use AsyncPHP\Assistant\Decorator\TaskDecorator;
use AsyncPHP\Assistant\Proxy;
use AsyncPHP\Assistant\Proxy\DoormanRemitProxy;
use AsyncPHP\Assistant\Tests\Test;
use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Remit\Location\InMemoryLocation;
use AsyncPHP\Remit\Server\ZeroMqServer;

/**
 * @covers AsyncPHP\Assistant\Proxy\DoormanRemitProxy
 */
class DoormanRemitProxyTest extends Test
{
    /**
     * @test
     */
    public function isRunsTasksInParallel()
    {
        $this->unlink(__DIR__ . "/parallel1.temp");
        $this->unlink(__DIR__ . "/parallel2.temp");
        $this->unlink(__DIR__ . "/parallel3.temp");

        $valid = true;

        $proxy = new DoormanRemitProxy(
            new ProcessManager(),
            new ZeroMqServer(
                new InMemoryLocation("127.0.0.1", 5556)
            )
        );

        $proxy
            ->parallel(function () {
                touch(__DIR__ . "/parallel1.temp");
            })
            ->synchronous(function () use (&$valid) {
                if (!file_exists(__DIR__ . "/parallel1.temp")) {
                    $valid = false;
                }

                touch(__DIR__ . "/synchronous1.temp");
            })
            ->parallel(array(
                function () {
                    touch(__DIR__ . "/parallel2.temp");
                },
                function () {
                    touch(__DIR__ . "/parallel3.temp");
                },
            ))
            ->synchronous(array(
                function () use (&$valid) {
                    if (!file_exists(__DIR__ . "/parallel2.temp") || !file_exists(__DIR__ . "/parallel3.temp")) {
                        $valid = false;
                    }

                    touch(__DIR__ . "/synchronous2.temp");
                },
                function () use (&$valid) {
                    if (!file_exists(__DIR__ . "/synchronous2.temp")) {
                        $valid = false;
                    }
                },
            ));

        while ($proxy->tick()) {
            usleep(25000);
        }

        $this->assertTrue($valid);

        $this->unlink(__DIR__ . "/parallel1.temp");
        $this->unlink(__DIR__ . "/parallel2.temp");
        $this->unlink(__DIR__ . "/parallel3.temp");
    }

    /**
     * @test
     */
    public function itEmitsEvents()
    {
        $proxy = new DoormanRemitProxy(
            new ProcessManager(),
            new ZeroMqServer(
                new InMemoryLocation("127.0.0.1", 5557)
            )
        );

        $passes = false;

        $proxy->addListener("custom event", function ($value) use (&$passes) {
            $passes = $value;
        });

        $proxy->parallel(function (Handler $handler, TaskDecorator $task) {
            $task->emit("custom event", array(true));
            sleep(1);
        });

        while ($proxy->tick()) {
            usleep(25000);
        }

        $this->assertTrue($passes);
    }
}
