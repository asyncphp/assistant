<?php

namespace AsyncPHP\Assistant;

use AsyncPHP\Doorman\Task;

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
     * Executes a single processing cycle. This should be run repeatedly, and will return false when there are no more running or waiting processes.
     *
     * @return bool
     */
    public function tick();
}
