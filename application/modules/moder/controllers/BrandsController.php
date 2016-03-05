<?php

use Application\Model\DbTable\BrandLink;
use Application\Model\Message;
use Autowp\Filter\Filename\Safe;

class Moder_BrandsController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    private function getLanguages()
    {
        return array(
            'ru', 'en', 'fr'
        );
    }

    /**
     * @param Brands_Row $car
     * @return string
     */
    private function brandModerUrl(Brands_Row $brand)
    {
        return $this->_helper->url('brand', 'brands', 'moder', array(
            'brand_id'  => $brand->id
        ));
    }

    private function getDescriptionForm()
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

    public function brandAction()
    {
        $brand = $this->_helper->catalogue()->getBrandTable()->find($this->_getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $canEdit = $this->_helper->user()->isAllowed('brand', 'edit');
        $canLogo = $this->_helper->user()->isAllowed('brand', 'logo');
        $canDeleteModel = $this->_helper->user()->isAllowed('model', 'delete');

        $this->view->picture = null;

        $request = $this->getRequest();

        if ($canEdit) {
            $form = new Application_Form_Moder_Brand_Edit(array(
                'languages' => $this->getLanguages(),
                'action'    => $this->_helper->url->url(array(
                    'form' => 'edit'
                ))
            ));

            $values = array(
                'caption'      => $brand->caption,
                'full_caption' => $brand->full_caption,
            );

            $brandLangTable = new Brand_Language();
            foreach ($this->getLanguages() as $language) {
                $brandLangRow = $brandLangTable->fetchRow(array(
                    'brand_id = ?' => $brand->id,
                    'language = ?' => $language
                ));
                if ($brandLangRow) {
                    $values['name' . $language] = $brandLangRow->name;
                }
            }

            $form->populate($values);

            if ($request->isPost() && $this->_getParam('form') == 'edit' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $brand->setFromArray(array(
                    'full_caption'  => $values['full_caption'],
                ));

                $brand->save();

                foreach ($this->getLanguages() as $language) {
                    $value = $values['name' . $language];
                    $brandLangRow = $brandLangTable->fetchRow(array(
                        'brand_id = ?' => $brand->id,
                        'language = ?' => $language
                    ));
                    if ($value) {
                        if (!$brandLangRow) {
                            $brandLangRow = $brandLangTable->fetchNew();
                            $brandLangRow->setFromArray(array(
                                'brand_id' => $brand->id,
                                'language' => $language
                            ));
                        }
                        $brandLangRow->name = $value;
                        $brandLangRow->save();
                    } else {
                        if ($brandLangRow) {
                            $brandLangRow->delete();
                        }
                    }
                }

                $this->_helper->log(sprintf(
                    'Редактирование информации о %s',
                    $this->view->htmlA($this->brandModerUrl($brand), $brand->caption)
                ), $brand);

                return $this->_redirect($this->_helper->url->url(array(
                    'form' => null
                )));
            }

            $this->view->formBrandEdit = $form;
        }

        if ($canLogo) {
            $form = new Application_Form_Moder_Brand_Logo(array(
                'action' => $this->_helper->url->url(array(
                    'form' => 'logo'
                ))
            ));

            if ($request->isPost() && $this->_getParam('form') == 'logo' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $tempFilepath = $form->logo->getFileName();

                $imageStorage = $this->_helper->imageStorage();

                $oldImageId = $brand->img;

                $newImageId = $imageStorage->addImageFromFile($tempFilepath, 'brand');
                $brand->img = $newImageId;
                $brand->save();

                if ($oldImageId) {
                    $imageStorage->removeImage($oldImageId);
                }

                $this->_helper->log(sprintf(
                    'Закачен логотип %s',
                    $this->view->htmlA($this->brandModerUrl($brand), $brand->caption)
                ), $brand);

                $this->_helper->flashMessenger->addMessage('Логотип сохранен');

                return $this->_redirect($this->view->url(array()));
            }
            $this->view->formLogo = $form;
        }

        $cars = new Cars();
        $this->view->cars = $cars->fetchAll(
            $cars
                ->select()
                ->from($cars)
                ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->order('cars.caption')
        );

        $descriptionForm = null;
        if ($canEdit) {
            $descriptionForm = $this->getDescriptionForm();
        }

        if ($brand->text_id) {
            $textStorage = $this->_helper->textStorage();
            $description = $textStorage->getText($brand->text_id);
            if ($canEdit) {
                $descriptionForm->populate(array(
                    'markdown' => $description
                ));
            }
        } else {
            $description = '';
        }
        
        $linkTable = new BrandLink();
        $linkRows = $linkTable->fetchAll([
            'brandId = ?' => $brand->id
        ]);
        
        $links = [];
        foreach ($linkRows as $link) {
            $links[] = [
                'id'      => $link->id,
                'caption' => $link->caption,
                'url'     => $link->url,
                'type'    => $link->type
            ];
        }

        $this->view->assign(array(
            'brand'                 => $brand,
            'canEdit'               => $canEdit,
            'canDeleteModel'        => $canDeleteModel,
            'canLogo'               => $canLogo,
            'description'           => $description,
            'descriptionForm'       => $descriptionForm,
            'links'                 => $links
        ));
    }

    public function saveLinksAction()
    {
        $brand = $this->_helper->catalogue()->getBrandTable()->find($this->_getParam('brand_id'))->current();
        if (!$brand)
            return $this->_forward('notfound', 'error');

        $canEdit = $this->_helper->user()->isAllowed('brand', 'edit');

        $links = new BrandLink();

        foreach ($this->getParam('link') as $id => $link) {
            $row = $links->find($id)->current();
            if ($row) {
                if (strlen($link['url'])) {
                    $row->caption = $link['caption'];
                    $row->url = $link['url'];
                    $row->type = $link['type'];

                    $row->save();
                } else {
                    $row->delete();
                }
            }
        }

        if ($new = $this->_getParam('new')) {
            if (strlen($new['url'])) {
                $row = $links->fetchNew();
                $row->brandId = $brand->id;
                $row->caption = $new['caption'];
                $row->url = $new['url'];
                $row->type = $new['type'];

                $row->save();
            }
        }

        return $this->_redirect($this->brandModerUrl($brand));
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

        $brand = $this->_helper->catalogue()->getBrandTable()->find($this->getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $form = $this->getDescriptionForm();

        if ($form->isValid($request->getPost())) {

            $values = $form->getValues();

            $text = $values['markdown'];

            $textStorage = $this->_helper->textStorage();

            if ($brand->text_id) {
                $textStorage->setText($brand->text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $textStorage->createText($text, $user->id);
                $brand->text_id = $textId;
                $brand->save();
            }


            $this->_helper->log(sprintf(
                'Редактирование описания бренда %s',
                $this->view->htmlA($this->brandModerUrl($brand), $brand->caption)
            ), $brand);

            if ($brand->text_id) {
                $userIds = $textStorage->getTextUserIds($brand->text_id);
                $message = sprintf(
                    'Пользователь %s редактировал описание бренда %s ( %s )',
                    $this->view->serverUrl($user->getAboutUrl()),
                    $brand->caption,
                    $this->view->serverUrl($this->brandModerUrl($brand))
                );

                $mModel = new Message();
                $userTable = new Users();
                foreach ($userIds as $userId) {
                    if ($userId != $user->id) {
                        foreach ($userTable->find($userId) as $userRow) {
                            $mModel->send(null, $userRow->id, $message);
                        }
                    }
                }
            }
        }

        return $this->_redirect($this->brandModerUrl($brand));
    }
}