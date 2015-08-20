<?php

namespace AsyncPHP\Assistant\Tests\Decorator;

use AsyncPHP\Assistant\Decorator\TaskDecorator;
use AsyncPHP\Assistant\Tests\Test;
use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Doorman\Task;
use AsyncPHP\Doorman\Task\CallbackTask;
use AsyncPHP\Doorman\Task\ProcessCallbackTask;
use AsyncPHP\Remit\Client\ZeroMqClient;
use AsyncPHP\Remit\Location\InMemoryLocation;
use AsyncPHP\Remit\Server\ZeroMqServer;

/**
 * @covers AsyncPHP\Assistant\Decorator\TaskDecorator
 */
class TaskDecoratorTest extends Test
{
    /**
     * @return ZeroMqServer
     */
    protected function newZeroMqServer()
    {
        return new ZeroMqServer(
            new InMemoryLocation("127.0.0.1", 5555)
        );
    }

    /**
     * @return ZeroMqClient
     */
    protected function newZeroMqClient()
    {
        return new ZeroMqClient(
            new InMemoryLocation("127.0.0.1", 5555)
        );
    }

    /**
     * @test
     */
    public function itSerializes()
    {
        $decorator = new TaskDecorator(
            new CallbackTask(function(){
                print "hello world";
            }),
            $this->newZeroMqClient()
        );

        $this->assertEquals(
            $decorator,
            unserialize(serialize($decorator))
        );
    }

    /**
     * @test
     */
    public function itEmitsEvents()
    {
        $task = new ProcessCallbackTask(function (Handler $handler, Task $task){
            $task->emit("custom event");
            sleep(1);
        });

        $decorator = new TaskDecorator($task, $this->newZeroMqClient());

        $passes = false;

        $server = $this->newZeroMqServer();
        $server->addListener("custom event", function ()  use (&$passes) {
            $passes = true;
        });

        $manager = new ProcessManager();
        $manager->addTask($decorator);

        while ($manager->tick() && !$passes) {
            $server->tick();
            usleep(25000);
        }

        $this->assertTrue($passes);
    }
}
