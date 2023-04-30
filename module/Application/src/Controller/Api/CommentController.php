<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function Autowp\Commons\currentFromResultSetInterface;
use function get_object_vars;
use function is_numeric;
use function strlen;

/**
 * @method UserPlugin user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class CommentController extends AbstractRestfulController
{
    private Comments $comments;

    private AbstractRestHydrator $hydrator;

    private TableGateway $userTable;

    private InputFilter $listInputFilter;

    private InputFilter $publicListInputFilter;

    private InputFilter $getInputFilter;

    public function __construct(
        Comments $comments,
        AbstractRestHydrator $hydrator,
        TableGateway $userTable,
        InputFilter $listInputFilter,
        InputFilter $publicListInputFilter,
        InputFilter $getInputFilter
    ) {
        $this->comments              = $comments;
        $this->hydrator              = $hydrator;
        $this->userTable             = $userTable;
        $this->listInputFilter       = $listInputFilter;
        $this->publicListInputFilter = $publicListInputFilter;
        $this->getInputFilter        = $getInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function indexAction()
    {
        $user = $this->user()->get();

        $isModer = $this->user()->enforce('global', 'moderate');

        $inputFilter = $isModer ? $this->listInputFilter : $this->publicListInputFilter;

        $inputFilter->setData($this->params()->fromQuery());

        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $values = $inputFilter->getValues();

        $options = [
            'order' => 'comment_message.datetime DESC',
        ];

        if ($values['item_id']) {
            $options['item_id'] = $values['item_id'];
        }

        if ($values['type_id']) {
            $options['type'] = $values['type_id'];
        }

        if ($values['parent_id']) {
            $options['parent_id'] = $values['parent_id'];
        }

        if ($values['no_parents']) {
            $options['no_parents'] = $values['no_parents'];
        }

        if ($values['user_id']) {
            $options['user'] = $values['user_id'];
        }

        switch ($values['order']) {
            case 'vote_desc':
                $options['order'] = ['comment_message.vote DESC', 'comment_message.datetime DESC'];
                break;
            case 'vote_asc':
                $options['order'] = ['comment_message.vote ASC', 'comment_message.datetime DESC'];
                break;
            case 'date_desc':
                $options['order'] = 'comment_message.datetime DESC';
                break;
            case 'date_asc':
            default:
                $options['order'] = 'comment_message.datetime ASC';
                break;
        }

        if ($isModer) {
            if ($values['user']) {
                if (! is_numeric($values['user'])) {
                    $userRow = currentFromResultSetInterface($this->userTable->select(['identity' => $values['user']]));
                    if ($userRow) {
                        $values['user'] = $userRow['id'];
                    }
                }

                $options['user'] = $values['user'];
            }

            if (strlen($values['moderator_attention'])) {
                $options['attention'] = $values['moderator_attention'];
            }

            if ($values['pictures_of_item_id']) {
                $options['type']     = Comments::PICTURES_TYPE_ID;
                $options['callback'] = function (Sql\Select $select) use ($values): void {
                    $select
                        ->join('pictures', 'comment_message.item_id = pictures.id', [])
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                        ->where(['item_parent_cache.parent_id' => $values['pictures_of_item_id']]);
                };
            }
        } else {
            if (! $values['item_id'] && ! $values['user_id']) {
                return new ApiProblemResponse(
                    new ApiProblem(
                        400,
                        'Data is invalid. Check `detail`.',
                        null,
                        'Validation error',
                        [
                            'invalid_params' => [
                                'item_id' => [
                                    'invalid' => 'item_id or user_id is required',
                                ],
                            ],
                        ]
                    )
                );
            }
        }

        $paginator = $this->comments->service()->getMessagesPaginator($options);

        $limit = null;
        if (strlen($values['limit']) > 0) {
            $limit = (int) $values['limit'];
            $limit = $limit >= 0 ? $limit : 0;
        }

        $paginator
            ->setItemCountPerPage($limit ?? 50000)
            ->setCurrentPageNumber($values['page']);

        $result = [
            'paginator' => get_object_vars($paginator->getPages()),
        ];

        if ($limit > 0 || $limit === null) {
            $this->hydrator->setOptions([
                'fields'   => $values['fields'],
                'language' => $this->language(),
                'user_id'  => $user ? $user['id'] : null,
            ]);

            $comments = [];
            foreach ($paginator->getCurrentItems() as $commentRow) {
                $comments[] = $this->hydrator->extract($commentRow);
            }

            if ($user && $values['item_id'] && $values['type_id']) {
                $this->comments->service()->setSubscriptionSent(
                    $values['type_id'],
                    $values['item_id'],
                    $user['id'],
                    false
                );
            }

            $result['items'] = $comments;
        }

        return new JsonModel($result);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function getAction()
    {
        $user = $this->user()->get();

        $this->getInputFilter->setData($this->params()->fromQuery());

        if (! $this->getInputFilter->isValid()) {
            return $this->inputFilterResponse($this->getInputFilter);
        }

        $values = $this->getInputFilter->getValues();

        //TODO: prevent load message from admin forum
        /** @psalm-suppress InvalidCast */
        $row = $this->comments->service()->getMessageRow((int) $this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'limit'    => $values['limit'],
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }
}
