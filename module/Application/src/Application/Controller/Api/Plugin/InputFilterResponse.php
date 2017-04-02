<?php

namespace Application\Controller\Api\Plugin;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

class InputFilterResponse extends AbstractPlugin
{
    public function __invoke(InputFilter $inputFilter)
    {
        return new ApiProblemResponse(
            new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                'invalid_params' => $inputFilter->getMessages()
            ])
        );
    }
}
