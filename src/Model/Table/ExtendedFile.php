<?php

declare(strict_types=1);

namespace Burzum\FileStorage\Model\Table;

use Phauthentic\Infrastructure\Storage\FileInterface;

// DEMO
class ExtendedFile extends \Phauthentic\Infrastructure\Storage\File
{
    /**
     * @var array
     */
    protected $demo = [];

    /**
     * @param string $name Name
     * @return \Phauthentic\Infrastructure\Storage\FileInterface
     */
    public function withDemo(string $name): FileInterface
    {
        $that = clone $this;
        $that->demo[$name] = [];

        return $that;
    }
}
