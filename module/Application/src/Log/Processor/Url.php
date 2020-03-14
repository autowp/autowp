<?php

namespace Application\Log\Processor;

use Laminas\Log\Processor\ProcessorInterface;

class Url implements ProcessorInterface
{
    public function process(array $event): array
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
