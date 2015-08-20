<?php

namespace AsyncPHP\Assistant\Proxy;

use AsyncPHP\Assistant\Handler\DoormanHandler;
use AsyncPHP\Assistant\Proxy;
use AsyncPHP\Assistant\Task\DoormanTask;
use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Manager;
use AsyncPHP\Doorman\Manager\GroupProcessManager;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Doorman\Task;
use AsyncPHP\Doorman\Task\ProcessCallbackTask;
use AsyncPHP\Remit\Client;
use AsyncPHP\Remit\Client\ZeroMqClient;
use AsyncPHP\Remit\Server;
use AsyncPHP\Remit\Server\ZeroMqServer;
use Closure;
use LogicException;

class DoormanProxy implements Proxy
{
    /**
     * @var GroupProcessManager
     */
    protected $manager;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var Client
     */
    protected $client;

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
        if ($manager instanceof ProcessManager) {
            $manager->setWorker(realpath(__DIR__ . "/../../bin/worker.php"));
        }

        if (!$manager instanceof GroupProcessManager) {
            $manager = new GroupProcessManager($manager);
        }

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
     * @return Client
     *
     * @throws LogicException
     */
    public function getClient()
    {
        if (!$this->client) {
            if ($this->server instanceof ZeroMqServer) {
                $this->client = new ZeroMqClient($this->server->getLocation());
            } else {
                throw new LogicException("Unsupported Remit Server");
            }
        }

        return $this->client;
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
        $this->server->tick();

        if ($this->manager->tick()) {
            return true;
        }

        $this->server->tick();

        if (empty($this->waiting)) {
            return false;
        }

        $next = array_shift($this->waiting);

        if ($next["type"] === "parallel") {
            $client = $this->getClient();

            $tasks = $next["tasks"];

            $tasks = array_map(function ($task) use ($client) {
                if ($task instanceof Task) {
                    return $task;
                }

                if ($task instanceof Closure) {
                    $task = new ProcessCallbackTask($task);
                }

                if ($task instanceof Task) {
                    return new DoormanTask($task, $client);
                }

                return $task;
            }, $tasks);

            $tasks = array_filter($tasks, function ($task) {
                return $task instanceof Task;
            });

            $this->manager->addTaskGroup($tasks);
        }

        if ($next["type"] === "synchronous") {
            foreach ($next["tasks"] as $task) {
                if ($task instanceof Task) {
                    $handler = $task->getHandler();

                    $object = new $handler();

                    if ($object instanceof Handler) {
                        $object = new DoormanHandler($object);
                        $object->handle($task);
                    }
                }

            }
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @param string  $name
     * @param Closure $closure
     *
     * @return $this
     */
    public function removeListener($name, Closure $closure)
    {
        $this->server->removeListener($name, $closure);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param string  $name
     * @param Closure $closure
     *
     * @return $this
     */
    public function addListener($name, Closure $closure)
    {
        $this->server->addListener($name, $closure);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return $this
     */
    public function emit($name, array $parameters = array())
    {
        $this->server->emit($name, $parameters);

        return $this;
    }
}
