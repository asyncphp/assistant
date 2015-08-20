<?php

namespace AsyncPHP\Assistant\Proxy;

use AsyncPHP\Assistant\Decorator\HandlerDecorator;
use AsyncPHP\Assistant\Decorator\TaskDecorator;
use AsyncPHP\Assistant\Proxy;
use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Manager;
use AsyncPHP\Doorman\Manager\GroupProcessManager;
use AsyncPHP\Doorman\Task;
use AsyncPHP\Doorman\Task\ProcessCallbackTask;
use AsyncPHP\Remit\Client;
use AsyncPHP\Remit\Client\ZeroMqClient;
use AsyncPHP\Remit\Server;
use AsyncPHP\Remit\Server\ZeroMqServer;
use LogicException;

class DoormanRemitProxy implements Proxy
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var array
     */
    protected $waiting = array();

    /**
     * @param Manager $manager
     * @param Server  $server
     */
    public function __construct(Manager $manager, Server $server)
    {
        if (!$manager instanceof GroupProcessManager) {
            $manager = new GroupProcessManager($manager);
        }

        $manager->setWorker(realpath(__DIR__."/../../bin/worker.php"));
        $manager->setLogPath(__DIR__);

        $this->manager = $manager;
        $this->server = $server;
    }

    /**
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @inheritdoc
     *
     * @param mixed $tasks
     *
     * @return $this
     */
    public function parallel($tasks)
    {
        return $this->addTasks("parallel", $tasks);
    }

    /**
     * @param string $type
     * @param mixed  $tasks
     *
     * @return $this
     */
    protected function addTasks($type, $tasks)
    {
        if (!is_array($tasks)) {
            $tasks = array($tasks);
        }

        $this->waiting[] = array(
            "type"  => $type,
            "tasks" => $tasks,
        );

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param mixed $tasks
     *
     * @return $this
     */
    public function synchronous($tasks)
    {
        return $this->addTasks("synchronous", $tasks);
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function tick()
    {
        if ($this->manager->tick()) {
            return true;
        }

        if (empty($this->waiting)) {
            return false;
        }

        $next = array_shift($this->waiting);

        if ($next["type"] === "parallel") {
            $client = $this->newRemitClient();

            $tasks = array_filter(array_map(function ($task) use ($client) {
                if ($task instanceof TaskDecorator) {
                    return $task;
                }

                if (is_callable($task)) {
                    $task = new ProcessCallbackTask($task);
                }

                if ($task instanceof Task) {
                    return new TaskDecorator($task, $client);
                }

                return null;
            }, $next["tasks"]));

            $this->manager->addTaskGroup($tasks);
        }

        if ($next["type"] === "synchronous") {
            foreach ($next["tasks"] as $task) {
                static::worker($task);
            }
        }

        return true;
    }

    /**
     * @return Client
     *
     * @throws LogicException
     */
    protected function newRemitClient()
    {
        if ($this->server instanceof ZeroMqServer) {
            return new ZeroMqClient($this->server->getLocation());
        }

        throw new LogicException("Unsupported Remit Server");
    }

    /**
     * @param mixed $task
     */
    public static function worker($task) {
        if ($task instanceof Task) {
            $handler = $task->getHandler();

            $object = new HandlerDecorator(new $handler());

            if ($object instanceof Handler) {
                $object->handle($task);
            }
        }
    }
}
