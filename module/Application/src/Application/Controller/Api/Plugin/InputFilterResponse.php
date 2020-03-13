<?php

namespace Application\Controller\Api\Plugin;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class InputFilterResponse extends AbstractPlugin
{
    public function __invoke(InputFilter $inputFilter): ApiProblemResponse
    {
        return new ApiProblemResponse(
            new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                'invalid_params' => $inputFilter->getMessages(),
            ])
        );
    }
}
