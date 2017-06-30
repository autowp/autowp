<?php

namespace Application\Controller\Api\Plugin;

use Zend\InputFilter\Input;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

class InputResponse extends AbstractPlugin
{
    public function __invoke(Input $input)
    {
        return new ApiProblemResponse(
            new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', $input->getMessages())
        );
    }
}
