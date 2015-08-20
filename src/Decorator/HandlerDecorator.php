<?php

namespace AsyncPHP\Assistant\Decorator;

use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Task;

class HandlerDecorator implements Handler
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
        if ($task instanceof TaskDecorator) {
            $this->emitTaskDecoratorId($task);

        }

        $this->handler->handle($task);
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
        return call_user_func_array([$this->handler, $method], $parameters);
    }

    /**
     * @param TaskDecorator $decorator
     */
    protected function emitTaskDecoratorId(TaskDecorator $decorator)
    {
        $id = getmypid();
        $hash = $decorator->getHash();

        $decorator->emit("assistant.pid", array($id, $hash));
    }
}
