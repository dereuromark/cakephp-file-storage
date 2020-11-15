<?php

declare(strict_types=1);

namespace Burzum\FileStorage\Model\Table;

// DEMO
class CleanExtendedFile extends CleanFile
{
    /**
     * @var array
     */
    protected $demo = [];

    /**
     * @param string $name Name
     * @return static
     */
    public function withDemo(string $name)
    {
        $that = clone $this;
        $that->demo[$name] = [];

        return $that;
    }
}
