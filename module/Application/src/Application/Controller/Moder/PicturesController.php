<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\DbTable\Picture;

class PicturesController extends AbstractActionController
{
    private $table;

    /**
     * @var Form
     */
    private $banForm;

    /**
     * @var TrafficControl
     */
    private $trafficControl;

    public function __construct(
        Picture $table,
        Form $banForm,
        TrafficControl $trafficControl
    ) {
        $this->table = $table;
        $this->banForm = $banForm;
        $this->trafficControl = $trafficControl;
    }

    public function pictureAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }

        $ban = false;
        $canBan = $this->user()->isAllowed('user', 'ban') && $picture->ip !== null && $picture->ip !== '';
        $canViewIp = $this->user()->isAllowed('user', 'ip');

        if ($canBan) {
            $ban = $this->trafficControl->getBanInfo(inet_ntop($picture->ip));
            if ($ban) {
                $userTable = new User();
                $ban['user'] = $userTable->find($ban['user_id'])->current();
            }
        }

        if ($canBan) {
            $this->banForm->setAttribute('action', $this->url()->fromRoute('ban/ban-ip', [
                'ip' => inet_ntop($picture->ip)
            ]));
            $this->banForm->populateValues([
                'submit' => 'ban/ban'
            ]);
        }

        return [
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canViewIp'       => $canViewIp,
            'banForm'         => $this->banForm,
        ];
    }
}
