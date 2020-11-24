<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 */
class AclController extends AbstractRestfulController
{
    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function isAllowedAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'result' => $this->user()->enforce(
                $this->params()->fromQuery('resource'),
                $this->params()->fromQuery('privilege')
            ),
        ]);
    }
}
