<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Forums\Forums;
use Autowp\User\Model\User;

use Application\Hydrator\Api\RestHydrator;

class ForumController extends AbstractRestfulController
{
    /**
     * @var Forums
     */
    private $forums;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var RestHydrator
     */
    private $themeHydrator;

    /**
     * @var InputFilter
     */
    private $themeListInputFilter;

    /**
     * @var InputFilter
     */
    private $themeInputFilter;

    /**
     * @var InputFilter
     */
    private $topicPutInputFilter;

    public function __construct(
        Forums $forums,
        User $userModel,
        RestHydrator $themeHydrator,
        InputFilter $themeListInputFilter,
        InputFilter $themeInputFilter,
        InputFilter $topicPutInputFilter
    ) {
        $this->forums = $forums;
        $this->userModel = $userModel;
        $this->themeHydrator = $themeHydrator;
        $this->themeListInputFilter = $themeListInputFilter;
        $this->themeInputFilter = $themeInputFilter;
        $this->topicPutInputFilter = $topicPutInputFilter;
    }

    public function userSummaryAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'subscriptionsCount' => $this->forums->getSubscribedTopicsCount($user['id'])
        ]);
    }

    public function getThemesAction()
    {
        $user = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $isModerator = $this->user()->inheritsRole('moder');

        $this->themeListInputFilter->setData($this->params()->fromQuery());

        if (! $this->themeListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->themeListInputFilter);
        }

        $data = $this->themeListInputFilter->getValues();

        $select = $this->forums->getThemeTable()->getSql()->select();
        $select->order('position');

        if ($data['theme_id']) {
            $select->where(['parent_id' => $data['theme_id']]);
        } else {
            $select->where(['parent_id IS NULL']);
        }

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $this->themeHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId
        ]);

        $items = [];
        foreach ($this->forums->getThemeTable()->selectWith($select) as $row) {
            $items[] = $this->themeHydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function getThemeAction()
    {
        $isModerator = $this->user()->inheritsRole('moder');

        $this->themeInputFilter->setData($this->params()->fromQuery());

        if (! $this->themeInputFilter->isValid()) {
            return $this->inputFilterResponse($this->themeInputFilter);
        }

        $data = $this->themeInputFilter->getValues();

        $select = $this->forums->getThemeTable()->getSql()->select()
            ->where(['id' => (int)$this->params('id')]);

        if (! $isModerator) {
            $select->where(['not is_moderator']);
        }

        $row = $this->forums->getThemeTable()->selectWith($select)->current();
        if (! $row) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();
        $userId = $user ? $user['id'] : null;

        $this->themeHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId,
            'topics'   => $data['topics']
        ]);

        return new JsonModel($this->themeHydrator->extract($row));
    }

    public function putTopicAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $forumAdmin = $this->user()->isAllowed('forums', 'moderate');
        if (! $forumAdmin) {
            return $this->forbiddenAction();
        }

        $row = $this->forums->getTopic((int)$this->params('id'));
        if (! $row) {
            return $this->notFoundAction();
        }

        $request = $this->getRequest();
        $data = (array)$this->processBodyContent($request);

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->topicPutInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'No fields provided'));
        }

        $this->topicPutInputFilter->setValidationGroup($fields);

        $this->topicPutInputFilter->setData($data);
        if (! $this->topicPutInputFilter->isValid()) {
            return $this->inputFilterResponse($this->topicPutInputFilter);
        }

        $values = $this->topicPutInputFilter->getValues();

        if (array_key_exists('status', $values)) {
            switch ($values['status']) {
                case Forums::STATUS_CLOSED:
                    $this->forums->close($row['id']);
                    break;
                case Forums::STATUS_DELETED:
                    $this->forums->delete($row['id']);
                    break;
                case Forums::STATUS_NORMAL:
                    $this->forums->open($row['id']);
                    break;
            }
        }

        return $this->getResponse()->setStatusCode(200);
    }
}
