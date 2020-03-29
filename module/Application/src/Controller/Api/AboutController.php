<?php

namespace Application\Controller\Api;

use Application\Model\Item;
use Application\Model\Picture;
use Autowp\Comments;
use Autowp\User\Model\User;
use Laminas\Db\Sql;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Permissions\Acl\Acl;
use Laminas\View\Model\JsonModel;

use function array_values;
use function ksort;
use function round;

use const SORT_NUMERIC;

class AboutController extends AbstractRestfulController
{
    private Acl $acl;

    private Comments\CommentsService $comments;

    private Picture $picture;

    private Item $item;

    private User $userModel;

    public function __construct(
        Acl $acl,
        Comments\CommentsService $comments,
        Picture $picture,
        Item $item,
        User $userModel
    ) {
        $this->acl       = $acl;
        $this->comments  = $comments;
        $this->picture   = $picture;
        $this->item      = $item;
        $this->userModel = $userModel;
    }

    public function indexAction(): JsonModel
    {
        $totalUsers = $this->userModel->getCount([]);
        $totalUsers = round($totalUsers, -3);

        $contributors = [];

        $greenUserRoles = [];
        foreach ($this->acl->getRoles() as $role) {
            if ($this->acl->isAllowed($role, 'status', 'be-green')) {
                $greenUserRoles[] = $role;
            }
        }

        if ($greenUserRoles) {
            $userTable = $this->userModel->getTable();

            $greenUsers = $userTable->select([
                'not deleted',
                new Sql\Predicate\In('role', $greenUserRoles),
                '(identity is null or identity <> "autowp")',
                'last_online > DATE_SUB(CURDATE(), INTERVAL 6 MONTH)',
            ]);

            foreach ($greenUsers as $greenUser) {
                $contributors[(int) $greenUser['id']] = (int) $greenUser['id'];
            }
        }

        $picturesUsers = $this->userModel->getRows([
            'not_deleted' => true,
            'order'       => 'pictures_total desc',
            'limit'       => 20,
        ]);

        foreach ($picturesUsers as $greenUser) {
            $contributors[(int) $greenUser['id']] = (int) $greenUser['id'];
        }

        ksort($contributors, SORT_NUMERIC);

        $totalPictures = $this->picture->getCount([]);
        $totalPictures = round($totalPictures, -4);

        $totalCars = $this->item->getCount([]);
        $totalCars = round($totalCars, -3);

        $totalComments = $this->comments->getTotalMessagesCount();
        $totalComments = round($totalComments, -3);

        return new JsonModel([
            'developer'        => 1,
            'fr_translator'    => 3282,
            'zh_translator'    => 25155,
            'be_translator'    => 15603,
            'pt_br_translator' => 17322,
            'contributors'     => array_values($contributors),
            'total_pictures'   => $totalPictures,
            'pictures_size'    => $this->picture->getTotalPicturesSize(),
            'total_users'      => $totalUsers,
            'total_cars'       => $totalCars,
            'total_comments'   => $totalComments,
        ]);
    }
}
