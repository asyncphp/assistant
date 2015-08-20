<?php

namespace AsyncPHP\Assistant\Tests\Proxy;

use AsyncPHP\Assistant\Proxy;
use AsyncPHP\Assistant\Proxy\DoormanRemitProxy;
use AsyncPHP\Assistant\Tests\Test;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Remit\Location\InMemoryLocation;
use AsyncPHP\Remit\Server\ZeroMqServer;

/**
 * @covers AsyncPHP\Assistant\Proxy\DoormanRemitProxy
 */
class DoormanRemitProxyTest extends Test
{
    /**
     * @var DoormanRemitProxy
     */
    protected $proxy;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->proxy = new DoormanRemitProxy(
            new ProcessManager(),
            new ZeroMqServer(
                new InMemoryLocation("127.0.0.1", 5555)
            )
        );
    }

    /**
     * @test
     */
    public function isRunsTasksInParallel()
    {
        @unlink(__DIR__ . "/parallel1.temp");
        @unlink(__DIR__ . "/parallel2.temp");
        @unlink(__DIR__ . "/parallel3.temp");

        $valid = true;

        $this->proxy
            ->parallel(function () {
                touch(__DIR__ . "/parallel1.temp");
                sleep(1);
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
                    sleep(1);
                },
                function () {
                    touch(__DIR__ . "/parallel3.temp");
                    sleep(1);
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

        while ($this->proxy->tick()) {
            usleep(25000);
        }

        $this->assertTrue($valid);
    }
}
