<?php

namespace AsyncPHP\Assistant\Tests;

use AsyncPHP\Assistant\Task\DoormanTask;
use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Doorman\Task\CallbackTask;
use AsyncPHP\Doorman\Task\ProcessCallbackTask;
use AsyncPHP\Remit\Client\ZeroMqClient;
use AsyncPHP\Remit\Location\InMemoryLocation;
use AsyncPHP\Remit\Server\ZeroMqServer;

/**
 * @covers AsyncPHP\Assistant\Task\DoormanTask
 */
class DoormanTaskTest extends Test
{
    /**
     * @test
     */
    public function itSerializes()
    {
        $task = new DoormanTask(
            new CallbackTask(function(){
                print "hello world";
            }),
            new ZeroMqClient(
                new InMemoryLocation("127.0.0.1", 5555)
            )
        );

        $this->assertEquals(
            $task,
            unserialize(serialize($task))
        );
    }

    /**
     * @test
     */
    public function itEmitsEvents()
    {
        $passes = false;

        $server = new ZeroMqServer(
            new InMemoryLocation("127.0.0.1", 5555)
        );

        $server->addListener("custom event", function ()  use (&$passes) {
            $passes = true;
        });

        $task = new DoormanTask(
            new ProcessCallbackTask(function (Handler $handler, DoormanTask $task){
                $task->emit("custom event");
                sleep(1);
            }),
            new ZeroMqClient(
                new InMemoryLocation("127.0.0.1", 5555)
            )
        );

        $manager = new ProcessManager();
        $manager->addTask($task);

        while ($manager->tick() && !$passes) {
            $server->tick();
            usleep(25000);
        }

        $this->assertTrue($passes);
    }
}
