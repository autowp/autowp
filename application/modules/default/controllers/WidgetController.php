<?php

class WidgetController extends Zend_Controller_Action
{
    public function picturePreviewAction()
    {
        $pictures = $this->_helper->catalogue()->getPictureTable();
        $picture = $pictures->find($this->getParam('picture_id'))->current();

        if (!$picture) {
            return $this->_forward('notfound', 'error');
        }

        return $this->_helper->json(array(
            'ok'    =>  true,
            'html'  =>  $this->view->picture($picture, array(
                'behaviour' => true
            ))
        ));
    }
}