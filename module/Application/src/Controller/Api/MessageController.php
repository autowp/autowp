<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\Message\MessageService;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function get_object_vars;

/**
 * @method UserPlugin user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 */
class MessageController extends AbstractRestfulController
{
    private MessageService $message;

    private AbstractRestHydrator $hydrator;

    private InputFilter $listInputFilter;

    public function __construct(
        AbstractRestHydrator $hydrator,
        MessageService $message,
        InputFilter $listInputFilter
    ) {
        $this->message         = $message;
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function indexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $params = $this->listInputFilter->getValues();

        $messages = [];
        switch ($params['folder']) {
            case 'inbox':
                $messages = $this->message->getInbox($user['id'], (int) $params['page']);
                break;
            case 'sent':
                $messages = $this->message->getSentbox($user['id'], (int) $params['page']);
                break;
            case 'system':
                $messages = $this->message->getSystembox($user['id'], (int) $params['page']);
                break;
            case 'dialog':
                $messages = $this->message->getDialogbox(
                    $user['id'],
                    (int) $params['user_id'],
                    (int) $params['page']
                );
                break;
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $params['fields'],
            'user_id'  => $user['id'],
        ]);

        $items = [];
        foreach ($messages['messages'] as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($messages['paginator']->getPages()),
            'items'     => $items,
        ]);
    }
}
