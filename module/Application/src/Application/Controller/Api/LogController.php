<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\Log;

class LogController extends AbstractActionController
{
    /**
     * @var Log
     */
    private $log;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    public function __construct(
        Log $log,
        RestHydrator $hydrator,
        InputFilter $listInputFilter
    ) {
        $this->log = $log;
        $this->hydrator = $hydrator;
        $this->listInputFilter = $listInputFilter;
    }

    public function indexAction()
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $this->user()->get();

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $params = $this->listInputFilter->getValues();

        $data = $this->log->getList([
            'article_id' => $params['article_id'],
            'item_id'    => $params['item_id'],
            'picture_id' => $params['picture_id'],
            'user_id'    => $params['user_id'],
            'page'       => $params['page'],
            'language'   => $this->language()
        ]);

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $params['fields'],
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($data['events'] as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($data['paginator']->getPages()),
            'items'     => $items
        ]);
    }
}
