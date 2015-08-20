<?php

namespace AsyncPHP\Assistant\Decorator;

use AsyncPHP\Doorman\Cancellable;
use AsyncPHP\Doorman\Expires;
use AsyncPHP\Doorman\Process;
use AsyncPHP\Doorman\Task;
use AsyncPHP\Remit\Client;

class TaskDecorator implements Cancellable, Expires, Process, Task
{
    /**
     * @var Task
     */
    protected $task;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var null|string
     */
    protected $hash;

    /**
     * @param Task   $task
     * @param Client $client
     */
    public function __construct(Task $task, Client $client)
    {
        $this->task = $task;
        $this->client = $client;
    }

    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return null|string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     *
     * @return $this
     */
    public function setHash($hash)
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @see CallbackTask::serialize()
     *
     * @return string
     */
    public function serialize()
    {
        return serialize(array(
            "task"   => $this->task,
            "client" => $this->client,
        ));
    }

    /**
     * @inheritdoc
     *
     * @see CallbackTask::unserialize()
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);

        $this->task = $unserialized["task"];
        $this->client = $unserialized["client"];
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getHandler()
    {
        return $this->task->getHandler();
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getData()
    {
        return $this->task->getData();
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function ignoresRules()
    {
        return $this->task->ignoresRules();
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function stopsSiblings()
    {
        return $this->task->stopsSiblings();
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function canRunTask()
    {
        return $this->task->canRunTask();
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function getExpiresIn()
    {
        if ($this->task instanceof Expires) {
            return $this->task->getExpiresIn();
        }

        return -1;
    }

    /**
     * @inheritdoc
     *
     * @param int $startedAt
     *
     * @return bool
     */
    public function shouldExpire($startedAt)
    {
        if ($this->task instanceof Expires) {
            return $this->task->shouldExpire($startedAt);
        }

        return true;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        if ($this->task instanceof Process) {
            return $this->task->setId($id);
        }

        return $this;
    }

    /**
     * @return null|int
     */
    public function getId()
    {
        if ($this->task instanceof Process) {
            return $this->task->getId();
        }

        return null;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function isCancelled()
    {
        if ($this->task instanceof Cancellable) {
            return $this->task->isCancelled();
        }

        return false;
    }

    /**
     * Passes missing method calls to the decorated task.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, array $parameters = array())
    {
        return call_user_func_array([$this->task, $method], $parameters);
    }

    /**
     * Emits an event through the stored Remit client.
     *
     * @param string $name
     * @param array  $parameters
     */
    public function emit($name, array $parameters = array())
    {
        $this->client->emit($name, $parameters);
    }
}
