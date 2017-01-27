<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Votings;

class VotingController extends AbstractActionController
{
    /**
     * @var Votings\Votings
     */
    private $service;

    public function __construct(Votings\Votings $service)
    {
        $this->service = $service;
    }

    public function votingAction()
    {
        $id = $this->params('id');
        $filter = (int)$this->params('filter');

        $user = $this->user()->get();

        $data = $this->service->getVoting($id, $filter, $user ? $user['id'] : null);

        if (! $data) {
            return $this->notFoundAction();
        }

        return $data;
    }

    public function votingVariantVotesAction()
    {
        $data = $this->service->getVotes($this->params('id'));
        if (! $data) {
            return $this->notFoundAction();
        }

        $viewModel = new ViewModel($data);
        return $viewModel->setTerminal($this->getRequest()->isXmlHttpRequest());
    }

    public function voteAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $id = (int)$this->params('id');

        $success = $this->service->vote(
            $id,
            $this->params()->fromPost('variant'),
            $user['id']
        );
        if (! $success) {
            return $this->notFoundAction();
        }

        return $this->redirect()->toRoute('votings/voting', [
            'action' => 'voting',
            'id'     => $id
        ]);
    }
}
