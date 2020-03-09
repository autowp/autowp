<?php

namespace Application\Controller\Api\Plugin;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\Input;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class InputResponse extends AbstractPlugin
{
    public function __invoke(Input $input)
    {
        return new ApiProblemResponse(
            new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', $input->getMessages())
        );
    }
}
