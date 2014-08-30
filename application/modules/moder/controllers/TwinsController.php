<?php
class Moder_TwinsController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    /**
     * @param My_Db_Table_Row $car
     * @return string
     */
    private function twinsGroupModerUrl($group)
    {
        return $this->_helper->url->url(array(
            'module'          => 'moder',
            'controller'      => 'twins',
            'action'          => 'twins-group',
            'twins_group_id'  => $group->id
        ), 'default', true);
    }

    public function twinsGroupAction()
    {
        $table = new Twins_Groups();
        $group = $table->find($this->_getParam('twins_group_id'))->current();
        if (!$group) {
            return $this->_forward('notfound', 'error');
        }

        $canEdit = $this->_helper->user()->isAllowed('twins', 'edit');

        if ($canEdit) {
            $request = $this->getRequest();
            $form = new Application_Form_Moder_Twins_Group_Edit();
            $form->populate($group->toArray());
            if ($request->isPost() && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $group->setFromArray($values);
                $group->save();

                $this->_helper->log(sprintf(
                    'Рекдактирование группы близнецов %s',
                    $group->name
                ), array($group));

                return $this->_redirect($this->twinsGroupModerUrl($group));
            }
            $this->view->formEdit = $form;
        }

        $this->view->assign(array(
            'group'   => $group,
            'canEdit' => $canEdit
        ));
    }
}