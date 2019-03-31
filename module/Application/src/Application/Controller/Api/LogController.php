<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblemResponse;

use Autowp\User\Controller\Plugin\User;

use Application\Controller\Plugin\ForbiddenAction;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\Log;

/**
 * Class LogController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method ForbiddenAction forbiddenAction()
 * @method string language()
 */
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
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

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
