<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Hydrator\Api\RestHydrator;

class IpController extends AbstractRestfulController
{
    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var InputFilter
     */
    private $itemInputFilter;

    public function __construct(
        RestHydrator $hydrator,
        InputFilter $itemInputFilter
    ) {
        $this->hydrator = $hydrator;
        $this->itemInputFilter = $itemInputFilter;
    }

    public function itemAction()
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $this->user()->get();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => $data['fields']
        ]);

        return new JsonModel($this->hydrator->extract($this->params('ip')));
    }
}
