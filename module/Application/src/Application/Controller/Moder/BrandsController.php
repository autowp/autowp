<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\BrandLink;
use Application\Model\Message;

use Brand_Language;
use Brands_Row;
use Cars;
use Users;

class BrandsController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var Form
     */
    private $logoForm;

    /**
     * @var Form
     */
    private $descForm;

    public function __construct($textStorage, Form $logoForm, Form $descForm)
    {
        $this->textStorage = $textStorage;
        $this->logoForm = $logoForm;
        $this->descForm = $descForm;
    }

    private function getLanguages()
    {
        return [
            'ru', 'en', 'fr'
        ];
    }

    /**
     * @param Brands_Row $car
     * @return string
     */
    private function brandModerUrl(Brands_Row $brand, $forceCanonical)
    {
        return $this->url()->fromRoute('moder/brands/params', [
            'action'   => 'brand',
            'brand_id' => $brand->id
        ], [
            'force_canonical' => $forceCanonical
        ]);
    }

    public function brandAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $brand = $this->catalogue()->getBrandTable()->find($this->params('brand_id'))->current();
        if (!$brand) {
            return $this->notFoundAction();
        }

        $canEdit = $this->user()->isAllowed('brand', 'edit');
        $canLogo = $this->user()->isAllowed('brand', 'logo');
        $canDeleteModel = $this->user()->isAllowed('model', 'delete');

        $picture = null;
        $formBrandEdit = null;
        $formLogo = null;

        $request = $this->getRequest();

        if ($canEdit) {
            $form = new \Application\Form\Moder\Brand\Edit(null, [
                'languages' => $this->getLanguages()
            ]);
            $form->setAttribute('action', $this->url()->fromRoute('moder/brands/params', [
                'action'   => 'brand',
                'brand_id' => $brand->id,
                'form'     => 'edit'
            ]));

            $values = [
                'caption'      => $brand->caption,
                'full_caption' => $brand->full_caption,
            ];

            $brandLangTable = new Brand_Language();
            foreach ($this->getLanguages() as $language) {
                $brandLangRow = $brandLangTable->fetchRow([
                    'brand_id = ?' => $brand->id,
                    'language = ?' => $language
                ]);
                if ($brandLangRow) {
                    $values['name' . $language] = $brandLangRow->name;
                }
            }

            $form->setData($values);

            if ($request->isPost() && $this->params('form') == 'edit') {
                $form->setData($this->params()->fromPost());
                if ($form->isValid()) {
                    $values = $form->getData();

                    $brand->setFromArray([
                        'full_caption'  => $values['full_caption'],
                    ]);

                    $brand->save();

                    foreach ($this->getLanguages() as $language) {
                        $value = $values['name' . $language];
                        $brandLangRow = $brandLangTable->fetchRow([
                            'brand_id = ?' => $brand->id,
                            'language = ?' => $language
                        ]);
                        if ($value) {
                            if (!$brandLangRow) {
                                $brandLangRow = $brandLangTable->fetchNew();
                                $brandLangRow->setFromArray([
                                    'brand_id' => $brand->id,
                                    'language' => $language
                                ]);
                            }
                            $brandLangRow->name = $value;
                            $brandLangRow->save();
                        } else {
                            if ($brandLangRow) {
                                $brandLangRow->delete();
                            }
                        }
                    }

                    $this->log(sprintf(
                        'Редактирование информации о %s',
                        $brand->caption
                    ), $brand);

                    return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
                }
            }

            $formBrandEdit = $form;
        }

        if ($canLogo) {
            $this->logoForm->setAttribute('action', $this->url()->fromRoute('moder/brands/params', [
                'action'   => 'brand',
                'brand_id' => $brand->id,
                'form'     => 'logo'
            ]));

            if ($request->isPost() && $this->params('form') == 'logo') {
                $data = array_merge_recursive(
                    $this->getRequest()->getPost()->toArray(),
                    $this->getRequest()->getFiles()->toArray()
                );
                $this->logoForm->setData($data);
                if ($this->logoForm->isValid()) {
                    $tempFilepath = $data['logo']['tmp_name'];

                    $imageStorage = $this->imageStorage();

                    $oldImageId = $brand->img;

                    $newImageId = $imageStorage->addImageFromFile($tempFilepath, 'brand');
                    $brand->img = $newImageId;
                    $brand->save();

                    if ($oldImageId) {
                        $imageStorage->removeImage($oldImageId);
                    }

                    $this->log(sprintf(
                        'Закачен логотип %s',
                        htmlspecialchars($brand->caption)
                    ), $brand);

                    $this->flashMessenger()->addSuccessMessage('Логотип сохранен');

                    return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
                }
            }
        }

        $carTable = new Cars();
        $cars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->order('cars.caption')
        );

        $this->descForm->setAttribute('action', $this->url()->fromRoute('moder/brands/params', [
            'action'   => 'save-description',
            'brand_id' => $brand['id']
        ]));

        if ($brand->text_id) {
            $textStorage = $this->textStorage;
            $description = $textStorage->getText($brand->text_id);
            if ($canEdit) {
                $this->descForm->setData([
                    'markdown' => $description
                ]);
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

        return [
            'brand'                 => $brand,
            'canEdit'               => $canEdit,
            'canDeleteModel'        => $canDeleteModel,
            'canLogo'               => $canLogo,
            'description'           => $description,
            'descriptionForm'       => $this->descForm,
            'links'                 => $links,
            'formBrandEdit'         => $formBrandEdit,
            'formLogo'              => $this->logoForm,
            'cars'                  => $cars
        ];
    }

    public function saveLinksAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $brand = $this->catalogue()->getBrandTable()->find($this->params('brand_id'))->current();
        if (!$brand) {
            return $this->notFoundAction();
        }

        $canEdit = $this->user()->isAllowed('brand', 'edit');

        $links = new BrandLink();

        foreach ($this->params()->fromPost('link') as $id => $link) {
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

        if ($new = $this->params()->fromPost('new')) {
            if (strlen($new['url'])) {
                $row = $links->fetchNew();
                $row->brandId = $brand->id;
                $row->caption = $new['caption'];
                $row->url = $new['url'];
                $row->type = $new['type'];

                $row->save();
            }
        }

        return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
    }

    public function saveDescriptionAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $canEdit = $this->user()->isAllowed('brand', 'edit');
        if (!$canEdit) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->forbiddenAction();
        }

        $brand = $this->catalogue()->getBrandTable()->find($this->params('brand_id'))->current();
        if (!$brand) {
            return $this->notFoundAction();
        }

        $this->descForm->setData($this->params()->fromPost());
        if ($this->descForm->isValid()) {

            $values = $this->descForm->getData();

            $text = $values['markdown'];

            $textStorage = $this->textStorage;

            if ($brand->text_id) {
                $textStorage->setText($brand->text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $textStorage->createText($text, $user->id);
                $brand->text_id = $textId;
                $brand->save();
            }


            $this->log(sprintf(
                'Редактирование описания бренда %s',
                $brand->caption
            ), $brand);

            if ($brand->text_id) {
                $userIds = $textStorage->getTextUserIds($brand->text_id);
                $message = sprintf(
                    'Пользователь %s редактировал описание бренда %s ( %s )',
                    $this->url()->fromRoute('users/user', [
                        'user_id' => $user->identity ? $user->identity : 'user' . $user->id
                    ], [
                        'force_canonical' => true
                    ]),
                    $brand->caption,
                    $this->brandModerUrl($brand, true)
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

        return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
    }
}