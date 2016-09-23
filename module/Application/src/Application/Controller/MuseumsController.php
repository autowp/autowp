<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Museum;

use geoPHP;

class MuseumsController extends AbstractActionController
{
    public function indexAction()
    {
        return $this->redirect()->toUrl('/map');
    }

    public function museumAction()
    {
        $table = new Museum();

        $museum = $table->find($this->params()->fromRoute('id'))->current();
        if (!$museum) {
            return $this->_forward('notfound', 'error');
        }

        $point = null;
        if ($museum->point) {
            $point = geoPHP::load(substr($museum->point, 4), 'wkb');
        }

        return [
            'museum' => $museum,
            'point'  => $point
        ];
    }
}