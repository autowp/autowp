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

    private function _getDescriptionForm()
    {
        return new Project_Form(array(
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->_helper->url->url(array(
                'action' => 'save-description'
            )),
            'decorators' => array(
                'PrepareElements',
                ['viewScript', array(
                    'viewScript' => 'forms/markdown.phtml'
                )],
                'Form'
            ),
            'elements' => [
                ['Brand_Description', 'markdown', array(
                    'required'   => false,
                    'decorators' => ['ViewHelper'],
                )],
            ]
        ));
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

                $group->setFromArray(array(
                    'name' => $values['name'],
                ));
                $group->save();

                $this->_helper->log(sprintf(
                    'Рекдактирование группы близнецов %s',
                    $group->name
                ), array($group));

                return $this->_redirect($this->twinsGroupModerUrl($group));
            }
            $this->view->formEdit = $form;
        }

        $descriptionForm = null;
        if ($canEdit) {
            $descriptionForm = $this->_getDescriptionForm();
        }

        if ($group->text_id) {
            $textStorage = $this->_helper->textStorage();
            $description = $textStorage->getText($group->text_id);
            if ($canEdit) {
                $descriptionForm->populate(array(
                    'markdown' => $description
                ));
            }
        } else {
            $description = '';
        }

        $this->view->assign(array(
            'group'   => $group,
            'canEdit' => $canEdit,
            'description'     => $description,
            'descriptionForm' => $descriptionForm
        ));
    }

    public function saveDescriptionAction()
    {
        $canEdit = $this->_helper->user()->isAllowed('brand', 'edit');
        if (!$canEdit) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $user = $this->_helper->user()->get();

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $table = new Twins_Groups();
        $group = $table->find($this->_getParam('twins_group_id'))->current();
        if (!$group) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $form = $this->_getDescriptionForm();

        if ($form->isValid($request->getPost())) {

            $values = $form->getValues();

            $text = $values['markdown'];

            $textStorage = $this->_helper->textStorage();

            if ($group->text_id) {
                $textStorage->setText($group->text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $textStorage->createText($text, $user->id);
                $group->text_id = $textId;
                $group->save();
            }


            $this->_helper->log(sprintf(
                'Редактирование описания группы близнецов %s',
                $this->view->htmlA($this->twinsGroupModerUrl($group), $group->name)
            ), $group);

            if ($group->text_id) {
                $userIds = $textStorage->getTextUserIds($group->text_id);
                $message = sprintf(
                    'Пользователь %s редактировал описание группы близнецов %s ( %s )',
                    $this->view->serverUrl($user->getAboutUrl()),
                    $group->name,
                    $this->view->serverUrl($this->twinsGroupModerUrl($group))
                );

                $userTable = new Users();
                foreach ($userIds as $userId) {
                    if ($userId != $user->id) {
                        foreach ($userTable->find($userId) as $userRow) {
                            $userRow->sendPersonalMessage(null, $message);
                        }
                    }
                }
            }
        }

        return $this->_redirect($this->twinsGroupModerUrl($group));
    }
}