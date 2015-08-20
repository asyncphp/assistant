<?php

namespace AsyncPHP\Assistant\Tests;

use PHPUnit_Framework_TestCase;

abstract class Test extends PHPUnit_Framework_TestCase
{
    /**
     * Safely deletes a file.
     *
     * @param string $path
     */
    protected function unlink($path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
