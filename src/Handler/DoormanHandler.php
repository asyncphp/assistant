<?php

namespace AsyncPHP\Assistant\Handler;

use AsyncPHP\Assistant\Task\DoormanTask;
use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Task;

class DoormanHandler implements Handler
{
    /**
     * @var Handler
     */
    protected $handler;

    /**
     * @param Handler $handler
     */
    public function __construct(Handler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @inheritdoc
     *
     * @param Task $task
     */
    public function handle(Task $task)
    {
        if ($task instanceof DoormanTask) {
            $this->emitTaskDecoratorId($task);
        }

        $this->handler->handle($task);
    }

    /**
     * @param DoormanTask $decorator
     */
    protected function emitTaskDecoratorId(DoormanTask $decorator)
    {
        $id = getmypid();
        $hash = $decorator->getHash();

        $decorator->emit("assistant.pid", array($id, $hash));
    }

    /**
     * Passes missing method calls to the decorated handler.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, array $parameters = array())
    {
        return call_user_func_array(array($this->handler, $method), $parameters);
    }
}
