<?php

namespace Application\Log\Processor;

use Zend\Log\Processor\ProcessorInterface;

class Url implements ProcessorInterface
{
    public function process(array $event)
    {
        if (isset($event['extra']['url'])) {
            return $event;
        }

        if (! isset($event['extra'])) {
            $event['extra'] = [];
        }

        /*if (isset($_SERVER['REQUEST_URI'])) {
            $event['extra']['url'] = $_SERVER['REQUEST_URI'];
        }*/

        return $event;
    }
}
