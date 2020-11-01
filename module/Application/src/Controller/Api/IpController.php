<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 */
class IpController extends AbstractRestfulController
{
    private AbstractRestHydrator $hydrator;

    private InputFilter $itemInputFilter;

    public function __construct(
        AbstractRestHydrator $hydrator,
        InputFilter $itemInputFilter
    ) {
        $this->hydrator        = $hydrator;
        $this->itemInputFilter = $itemInputFilter;
    }

    /**
     * @return ResponseInterface|ViewModel
     */
    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $user = $this->user()->get();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => $data['fields'],
        ]);

        return new JsonModel($this->hydrator->extract($this->params('ip')));
    }
}
