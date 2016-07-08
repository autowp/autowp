<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend_Date;
use Zend_Db_Expr;

use Voting;
use Voting_Variant;
use Voting_Variant_Vote;
use Users;

class VotingController extends AbstractActionController
{
    private function canVote($voting)
    {
        $canVote = false;

        if ($voting->getDate('begin_date')->isEarlier(Zend_Date::now())) {
            if ($voting->getDate('end_date')->isLater(Zend_Date::now())) {

                $user = $this->user()->get();
                if ($user) {
                    $vvvTable = new Voting_Variant_Vote();
                    $voted = (bool)$vvvTable->fetchRow(
                        $vvvTable->select(true)
                            ->join('voting_variant', 'voting_variant_vote.voting_variant_id = voting_variant.id', null)
                            ->where('voting_variant.voting_id = ?', $voting->id)
                            ->where('voting_variant_vote.user_id = ?', $user->id)
                    );

                    if (!$voted) {
                        $canVote = true;
                    }
                }
            }
        }

        return $canVote;
    }

    public function votingAction()
    {
        $vTable = new Voting();
        $vvTable = new Voting_Variant();

        $voting = $vTable->find($this->params('id'))->current();

        if (!$voting) {
            return $this->notFoundAction();
        }

        $filter = (int)$this->params('filter');
        $vvvTable = new Voting_Variant_Vote();

        $variants = [];
        $vvRows = $vvTable->fetchAll([
            'voting_id = ?' => $voting->id
        ], 'position');
        $maxVotes = $minVotes = null;
        foreach ($vvRows as $vvRow) {

            switch ($filter) {
                case 1:
                    $votes = $vvvTable->getAdapter()->fetchOne(
                        $vvvTable->getAdapter()->select()
                            ->from($vvvTable->info('name'), 'count(1)')
                            ->where('voting_variant_vote.voting_variant_id = ?', $vvRow->id)
                            ->join('users', 'voting_variant_vote.user_id = users.id', null)
                            ->where('users.pictures_added > 100')
                    );
                    break;

                default:
                    $votes = $vvRow->votes;
                    break;
            }

            $variants[] = [
                'id'    => $vvRow->id,
                'name'  => $vvRow->name,
                'text'  => $vvRow->text,
                'votes' => $votes,
            ];

            if (is_null($maxVotes) || $votes > $maxVotes) {
                $maxVotes = $votes;
            }
            if (is_null($minVotes) || $votes < $minVotes) {
                $minVotes = $votes;
            }
        }

        if ($maxVotes > 0) {
            $minVotesPercent = ceil(100 * $minVotes / $maxVotes);
        } else {
            $minVotesPercent = 0;
        }

        foreach ($variants as &$variant) {
            if ($maxVotes > 0) {
                $variant['percent'] = round(100*$variant['votes'] / $maxVotes, 2);
                $variant['isMax'] = $variant['percent'] >= 99;
                $variant['isMin'] = $variant['percent'] <= $minVotesPercent;
            } else {
                $variant['percent'] = 0;
                $variant['isMax'] = false;
                $variant['isMin'] = false;
            }
        }


        return [
            'canVote'  => $this->canVote($voting),
            'voting'   => $voting,
            'variants' => $variants,
            'maxVotes' => $maxVotes,
            'filter'   => $filter
        ];
    }

    public function votingVariantVotesAction()
    {
        $vvTable = new Voting_Variant();
        $variant = $vvTable->find($this->params('id'))->current();

        if (!$variant) {
            return $this->notFoundAction();
        }

        $vvvTable = new Voting_Variant_Vote();

        $uTable = new Users();
        $users = $uTable->fetchAll(
            $uTable->select(true)
                ->join('voting_variant_vote', 'users.id = voting_variant_vote.user_id', null)
                ->where('voting_variant_vote.voting_variant_id = ?', $variant->id)
        );

        $viewModel = new ViewModel([
            'users' => $users
        ]);

        $viewModel->setTerminal($this->getRequest()->isXmlHttpRequest());

        return $viewModel;
    }

    public function voteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->notFoundAction();
        }

        $vTable = new Voting();

        $voting = $vTable->find($this->params('id'))->current();

        if (!$voting) {
            return $this->notFoundAction();
        }

        if (!$this->canVote($voting)) {
            return $this->forbiddenAction();
        }

        $vvTable = new Voting_Variant();
        $vvRows = $vvTable->find($this->params()->fromPost('variant'));

        if (!$voting->multivariant) {
            if (count($vvRows) > 1) {
                return $this->forbiddenAction();
            }
        }

        $vvvTable = new Voting_Variant_Vote();
        $vvvAdapter = $vvvTable->getAdapter();

        $user = $this->user()->get();

        foreach ($vvRows as $vvRow) {
            if ($vvRow->voting_id != $voting->id) {
                continue;
            }

            $vvvRow = $vvvTable->fetchRow([
                'voting_variant_id = ?' => $vvRow->id,
                'user_id = ?'           => $user->id
            ]);
            if (!$vvvRow) {
                $vvvTable->insert([
                    'voting_variant_id' => $vvRow->id,
                    'user_id'           => $user->id,
                    'timestamp'         => new Zend_Db_Expr('now()')
                ]);
            }

            $vvRow->votes = $vvvAdapter->fetchOne(
                $vvvAdapter->select()
                    ->from($vvvTable->info('name'), 'count(1)')
                    ->where('voting_variant_id = ?', $vvRow->id)
            );
            $vvRow->save();
        }

        $voting->votes = $vvvAdapter->fetchOne(
            $vvvAdapter->select()
                ->from($vvvTable->info('name'), 'count(distinct voting_variant_vote.user_id)')
                ->join('voting_variant', 'voting_variant_vote.voting_variant_id = voting_variant.id', null)
                ->where('voting_variant.voting_id = ?', $voting->id)
        );
        $voting->save();

        return $this->redirect()->toUrl($this->url()->fromRoute('votings/voting', [
            'action' => 'voting',
            'id'     => $voting->id
        ]));
    }
}