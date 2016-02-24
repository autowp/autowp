<?php

use Application\Model\DbTable\Museum;

class MuseumsController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->_redirect('/map/');
    }

    public function museumAction()
    {
        $table = new Museum();

        $museum = $table->find($this->getParam('id'))->current();
        if (!$museum) {
            return $this->_forward('notfound', 'error');
        }

        $point = null;
        if ($museum->point) {
            $point = geoPHP::load(substr($museum->point, 4), 'wkb');
        }

        $this->view->assign(array(
            'museum' => $museum,
            'point'  => $point
        ));
    }
}