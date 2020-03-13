<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Log;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function get_object_vars;

/**
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method ViewModel forbiddenAction()
 * @method string language()
 */
class LogController extends AbstractActionController
{
    private Log $log;

    private AbstractRestHydrator $hydrator;

    private InputFilter $listInputFilter;

    public function __construct(
        Log $log,
        AbstractRestHydrator $hydrator,
        InputFilter $listInputFilter
    ) {
        $this->log             = $log;
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
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
            'language'   => $this->language(),
        ]);

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $params['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        $items = [];
        foreach ($data['events'] as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($data['paginator']->getPages()),
            'items'     => $items,
        ]);
    }
}
