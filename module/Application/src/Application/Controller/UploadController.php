<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Form\Upload as UploadForm;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\PictureService;

class UploadController extends AbstractActionController
{
    private $partial;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Brand
     */
    private $brand;

    /**
     * @var PictureService
     */
    private $pictureService;

    public function __construct(
        $partial,
        PictureItem $pictureItem,
        Perspective $perspective,
        ItemParent $itemParent,
        Item $item,
        Brand $brand,
        Picture $picture,
        PictureService $pictureService
    ) {
        $this->partial = $partial;
        $this->pictureItem = $pictureItem;
        $this->perspective = $perspective;
        $this->itemParent = $itemParent;
        $this->item = $item;
        $this->brand = $brand;
        $this->picture = $picture;
        $this->pictureService = $pictureService;
    }

    public function onlyRegisteredAction()
    {
    }

    public function indexAction()
    {
        $user = $this->user()->get();

        if (! $user || $user['deleted']) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'only-registered'
            ]);
        }

        $replace = $this->params('replace');
        $replacePicture = false;
        if ($replace) {
            $replacePicture = $this->picture->getRow([
                'identity' => (string)$replace
            ]);
        }

        $perspectiveId = null;

        if ($replacePicture) {
            $itemIds = $this->pictureItem->getPictureItems($replacePicture['id']);
        } else {
            $itemId = (int)$this->params('item_id');
            $itemIds = $itemId ? [$itemId] : [];
            $perspectiveId = (int)$this->params('perspective_id');
        }

        $selected = false;

        $names = [];
        foreach ($this->item->getRows(['id' => $itemIds]) as $item) {
            $selected = true;
            $names[] = $this->car()->formatName($item, $this->language());
        }
        $selectedName = implode(', ', $names);

        $form = null;

        if ($selected) {
            $form = new UploadForm(null, [
                'multipleFiles' => ! $replacePicture,
            ]);
            $form->setAttribute('action', $this->url()->fromRoute('upload/params', [
                'action' => 'send'
            ], [], true));
        }

        $perspectives = $this->perspective->getArray();

        foreach ($perspectives as &$perspective) {
            $perspective['name'] = $this->translate($perspective['name']);
        }
        unset($perspective);

        return [
            'form'         => $form,
            'selected'     => $selected,
            'selectedName' => $selectedName,
            'perspectives' => $perspectives
        ];
    }

    private function saveUpload($form, $itemIds, int $perspectiveId, int $replacePictureId)
    {
        $user = $this->user()->get();

        $values = $form->getData();

        $tempFilePaths = [];
        $data = $form->get('picture')->getValue();

        if ($form->get('picture')->getAttribute('multiple')) {
            foreach ($data as $file) {
                $tempFilePaths[] = $file['tmp_name'];
            }
        } else {
            $tempFilePaths[] = $data['tmp_name'];
        }

        $result = [];

        foreach ($tempFilePaths as $tempFilePath) {
            $picture = $this->pictureService->addPictureFromFile(
                $tempFilePath,
                $user['id'],
                $this->getRequest()->getServer('REMOTE_ADDR'),
                $itemIds,
                $perspectiveId,
                $replacePictureId,
                (string)$values['note']
            );

            $result[] = $picture;
        }

        return $result;
    }

    public function selectBrandAction()
    {
        $language = $this->language();

        $brand = $this->brand->getBrandById((int)$this->params('brand_id'), $language);
        if ($brand) {
            return $this->forward()->dispatch(self::class, [
                'action'   => 'select-in-brand',
                'brand_id' => $brand['id']
            ]);
        }

        $rows = $this->brand->getList($language, function () {
        });

        return [
            'brands' => $rows
        ];
    }

    public function selectInBrandAction()
    {
        $language = $this->language();

        $brand = $this->brand->getBrandById((int)$this->params('brand_id'), $language);

        if (! $brand) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'select-brand'
            ]);
        }

        $haveConcepts = (bool)$this->item->getRow([
            'ancestor'   => $brand['id'],
            'is_concept' => true,
            'limit'      => 1
        ]);

        $rows = $this->item->getRows([
            'language'     => $this->language(),
            'columns'      => ['id', 'name', 'is_group'],
            'item_type_id' => Item::VEHICLE,
            'is_concept'   => false,
            'parent'       => $brand['id'],
            'order'        => [
                'item.name',
                'item.begin_year',
                'item.end_year',
                'item.begin_model_year',
                'item.end_model_year'
            ]
        ]);

        $vehicles = $this->prepareCars($rows);

        $rows = $this->item->getRows([
            'language'     => $this->language(),
            'columns'      => ['id', 'name', 'is_group'],
            'item_type_id' => Item::ENGINE,
            'is_concept'   => false,
            'parent'       => $brand['id'],
            'order'        => [
                'item.name',
                'item.begin_year',
                'item.end_year',
                'item.begin_model_year',
                'item.end_model_year'
            ]
        ]);

        $engines = $this->prepareCars($rows);

        return [
            'brand'        => $brand,
            'vehicles'     => $vehicles,
            'engines'      => $engines,
            'haveConcepts' => $haveConcepts,
            'conceptsUrl'  => $this->url()->fromRoute('upload/params', [
                'action' => 'concepts',
            ], [], true),
        ];
    }

    private function prepareCars($rows)
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'begin_model_year' => $row['begin_model_year'],
                'end_model_year'   => $row['end_model_year'],
                'spec'             => $row['spec'],
                'spec_full'        => $row['spec_full'],
                'body'             => $row['body'],
                'name'             => $row['name'],
                'begin_year'       => $row['begin_year'],
                'end_year'         => $row['end_year'],
                'today'            => $row['today'],
                'url'  => $this->url()->fromRoute('upload/params', [
                    'action'  => 'index',
                    'item_id' => $row['id']
                ], [], true),
                'haveChilds' => $this->itemParent->hasChildItems($row['id']),
                'isGroup'    => $row['is_group'],
                'type'       => null,
                'loadUrl'    => $this->url()->fromRoute('upload/params', [
                    'action'  => 'car-childs',
                    'item_id' => $row['id']
                ], [], true),
            ];
        }

        return $items;
    }

    private function prepareCarParentRows($rows)
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'begin_model_year' => $row['begin_model_year'],
                'end_model_year'   => $row['end_model_year'],
                'spec'             => $row['spec'],
                'spec_full'        => $row['spec_full'],
                'body'             => $row['body'],
                'name'             => $row['name'],
                'begin_year'       => $row['begin_year'],
                'end_year'         => $row['end_year'],
                'today'            => $row['today'],
                'url'  => $this->url()->fromRoute('upload/params', [
                    'action'  => 'index',
                    'item_id' => $row['id']
                ], [], true),
                'haveChilds' => $this->itemParent->hasChildItems($row['id']),
                'isGroup'    => $row['is_group'],
                'type'       => $row['type'],
                'loadUrl'    => $this->url()->fromRoute('upload/params', [
                    'action'  => 'car-childs',
                    'item_id' => $row['id']
                ], [], true),
            ];
        }

        return $items;
    }

    public function carChildsAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'only-registered'
            ]);
        }

        $car = $this->item->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notfoundAction();
        }

        $rows = $this->item->getRows([
            'language' => $this->language(),
            'columns'  => ['id', 'name', 'is_group'],
            'parent'   => [
                'id'   => $car['id'],
                'columns' => [
                    'type' => 'link_type'
                ]
            ],
            'order'    => ['ip1.type', 'name', 'item.begin_year', 'item.end_year']
        ]);

        $viewModel = new ViewModel([
            'cars' => $this->prepareCarParentRows($rows)
        ]);

        return $viewModel->setTerminal(true);
    }

    public function conceptsAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'only-registered'
            ]);
        }

        $brand = $this->item->getRow([
            'item_type_id' => Item::BRAND,
            'id'           => (int)$this->params('brand_id')
        ]);
        if (! $brand) {
            return $this->notfoundAction();
        }

        $rows = $this->item->getRows([
            'language'   => $this->language(),
            'columns'    => ['id', 'name', 'is_group'],
            'parent'     => $brand['id'],
            'order'      => ['name', 'item.begin_year', 'item.end_year'],
            'is_concept' => true
        ]);

        $concepts = $this->prepareCars($rows);

        $viewModel = new ViewModel([
            'concepts' => $concepts,
        ]);

        return $viewModel->setTerminal(true);
    }

    public function cropSaveAction()
    {
        $picture = $this->picture->getRow(['id' => (int)$this->params()->fromPost('id')]);
        if (! $picture) {
            return $this->notfoundAction();
        }

        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        if ($picture['owner_id'] != $user['id']) {
            return $this->forbiddenAction();
        }

        if ($picture['status'] != Picture::STATUS_INBOX) {
            return $this->forbiddenAction();
        }

        $left = round($this->params()->fromPost('x'));
        $top = round($this->params()->fromPost('y'));
        $width = round($this->params()->fromPost('w'));
        $height = round($this->params()->fromPost('h'));

        $left = max(0, $left);
        $left = min($picture['width'], $left);
        $width = max(400, $width);
        $width = min($picture['width'], $width);

        $top = max(0, $top);
        $top = min($picture['height'], $top);
        $height = max(300, $height);
        $height = min($picture['height'], $height);

        if ($left > 0 || $top > 0 || $width < $picture['width'] || $height < $picture['height']) {
            $set = [
                'crop_left'   => $left,
                'crop_top'    => $top,
                'crop_width'  => $width,
                'crop_height' => $height
            ];
        } else {
            $set = [
                'crop_left'   => null,
                'crop_top'    => null,
                'crop_width'  => null,
                'crop_height' => null
            ];
        }
        $this->picture->getTable()->update($set, [
            'id' => $picture['id']
        ]);

        $picture = $this->picture->getRow(['id' => $picture['id']]);

        $this->imageStorage()->flush([
            'image' => $picture['image_id']
        ]);

        $this->log(sprintf(
            'Выделение области на картинке %s',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [
            'pictures' => $picture['id']
        ]);

        $image = $this->imageStorage()->getFormatedImage(
            $this->picture->getFormatRequest($picture),
            'picture-thumb'
        );

        return new JsonModel([
            'ok'  => true,
            'src' => $image->getSrc()
        ]);
    }

    public function sendAction()
    {
        $user = $this->user()->get();

        if (! $user || $user['deleted']) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'only-registered'
            ]);
        }

        $request = $this->getRequest();

        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        $replace = $this->params('replace');
        $replacePicture = false;
        if ($replace) {
            $replacePicture = $this->picture->getRow([
                'identity' => (string)$replace
            ]);
        }

        $perspectiveId = null;

        if ($replacePicture) {
            $itemIds = $this->pictureItem->getPictureItems($replacePicture['id']);
            if (count($itemIds) == 1) {
                $perspectiveId = $this->pictureItem->getPerspective($replacePicture['id'], $itemIds[0]);
            }
        } else {
            $itemId = (int)$this->params('item_id');
            $itemIds = $itemId ? [$itemId] : [];
            $perspectiveId = (int)$this->params('perspective_id');
        }

        $selectedItems = $this->item->getRows(['id' => $itemIds]);

        if (count($selectedItems) <= 0) {
            return $this->forbiddenAction();
        }

        $form = new UploadForm(null, [
            'multipleFiles' => ! $replacePicture,
        ]);

        $data = array_merge_recursive(
            $request->getPost()->toArray(),
            $request->getFiles()->toArray()
        );
        $form->setData($data);
        if (! $form->isValid()) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel($form->getMessages());
        }

        $pictures = $this->saveUpload($form, $itemIds, (int)$perspectiveId, $replacePicture ? $replacePicture['id'] : 0);

        $result = [];
        foreach ($pictures as $picture) {
            $image = $this->imageStorage()->getFormatedImage(
                $this->picture->getFormatRequest($picture),
                'picture-gallery-full'
            );

            if ($image) {
                $picturesData = $this->pic()->listData([$picture]);

                $html = $this->partial->__invoke('application/picture', array_replace(
                    $picturesData['items'][0],
                    [
                        'disableBehaviour' => false,
                        'isModer'          => false
                    ]
                ));

                $cPerspectiveId = null;
                $perspectiveUrl = null;
                if (count($itemIds) == 1) {
                    $itemId = $itemIds[0];
                    $cPerspectiveId = $this->pictureItem->getPerspective($picture['id'], $itemId);
                    $perspectiveUrl = $this->url()->fromRoute('api/picture-item/update', [
                        'picture_id' => $picture['id'],
                        'item_id'    => $itemId
                    ]);
                }

                $result[] = [
                    'id'     => $picture['id'],
                    'html'   => $html,
                    'width'  => $picture['width'],
                    'height' => $picture['height'],
                    'src'    => $image->getSrc(),
                    'perspectiveUrl' => $perspectiveUrl,
                    'perspectiveId'  => $cPerspectiveId
                ];
            }
        }

        $this->getResponse()->setStatusCode(200);
        return new JsonModel($result);
    }
}
