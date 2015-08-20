<?php

namespace AsyncPHP\Assistant;

use AsyncPHP\Doorman\Task;
use Closure;

interface Proxy
{
    /**
     * Handles one or more callable tasks in any order, in different processes.
     *
     * @param mixed $tasks
     */
    public function parallel($tasks);

    /**
     * Handles one or more callable tasks in a predictable order, and in the same process.
     *
     * @param mixed $tasks
     */
    public function synchronous($tasks);

    /**
     * Executes a single processing cycle. This should be run repeatedly, and will return false
     * when there are no more running or waiting processes.
     *
     * @return bool
     */
    public function tick();

    /**
     * @param string  $name
     * @param Closure $closure
     */
    public function removeListener($name, Closure $closure);

    /**
     * @param string  $name
     * @param Closure $closure
     */
    public function addListener($name, Closure $closure);

    /**
     * @param string $name
     * @param array  $parameters
     */
    public function emit($name, array $parameters = array());
}
