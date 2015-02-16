<?php
class InfoController extends Zend_Controller_Action
{
    public function specAction()
    {
        $table = new Spec();

        $rows = $table->fetchAll(null, 'short_name');

        $this->view->assign(array(
            'items' => $rows
        ));
    }
}