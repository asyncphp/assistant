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
use Closure;
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
        if (!$manager instanceof GroupProcessManager) {
            $manager = new GroupProcessManager($manager);
        }

        $manager->setWorker(realpath(__DIR__ . "/../../bin/worker.php"));

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
        $this->server->tick();

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

                if ($task instanceof Closure) {
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
     * @param mixed $task
     */
    public static function worker($task)
    {
        if ($task instanceof Task) {
            $handler = $task->getHandler();

            $object = new HandlerDecorator(new $handler());

            if ($object instanceof Handler) {
                $object->handle($task);
            }
        }
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
