<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\Categories;
use Application\Model\Item;
use Application\Model\Picture;

class CategoryController extends AbstractActionController
{
    private $cache;

    /**
     * @var Categories
     */
    private $categories;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        $cache,
        Categories $categories,
        Item $itemModel,
        Picture $picture
    ) {
        $this->cache = $cache;
        $this->categories = $categories;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/ng/category');
    }

    private function doCategoryAction($callback)
    {
        $language = $this->language();

        $currentCategory = $this->itemModel->getRow([
            'catname' => (string)$this->params('category_catname')
        ]);
        $isOther = (bool)$this->params('other');

        if (! $currentCategory) {
            return $this->notFoundAction();
        }

        $langName = $this->itemModel->getName($currentCategory['id'], $language);

        $breadcrumbs = [[
            'name' => $langName ? $langName : $currentCategory['name'],
            'url'  => '/ng/category/' . urlencode($currentCategory['catname'])
        ]];

        $topCategory = $currentCategory;

        while (true) {
            $parentCategory = $this->itemModel->getRow([
                'child' => $topCategory['id']
            ]);

            if (! $parentCategory) {
                break;
            }

            $topCategory = $parentCategory;

            $cLangName = $this->itemModel->getName($parentCategory['id'], $language);

            array_unshift($breadcrumbs, [
                'name' => $cLangName ? $cLangName : $parentCategory['name'],
                'url'  => '/ng/category/' . urlencode($parentCategory['catname']) . '/'
            ]);
        }

        $path = $this->params('path');
        $path = $path ? (array)$path : [];

        $currentCar = $currentCategory;

        $breadcrumbsPath = [];

        foreach ($path as $pathNode) {
            $childCar = $this->itemModel->getRow([
                'parent' => [
                    'id'           => $currentCar['id'],
                    'link_catname' => $pathNode
                ]
            ]);

            if (! $childCar) {
                return $this->notFoundAction();
            }

            $breadcrumbsPath[] = $pathNode;

            $breadcrumbs[] = [
                'name' => $this->car()->formatName($childCar, $language),
                'url'  => '/ng/category/' . urlencode($currentCategory['catname']) . '/' . ($breadcrumbsPath ? implode('/', $breadcrumbsPath) . '/' : '')
            ];

            $currentCar = $childCar;
        }

        $currentItem = $currentCar ? $currentCar : $currentCategory;
        $currentItemNameData = $this->itemModel->getNameData($currentItem, $language);

        $data = [
            'category'            => $currentCategory,
            'categoryName'        => $langName ? $langName : $currentCategory['name'],
            'isOther'             => $isOther,
            'currentItem'         => $currentItem,
            'currentItemNameData' => $currentItemNameData
        ];


        $result = $callback(
            $currentCategory,
            $currentCar,
            $isOther,
            $path,
            $breadcrumbs,
            $langName ? $langName : $currentCategory['name']
        );

        if (is_array($result)) {
            return array_replace($data, $result);
        }

        return $result;
    }

    public function categoryPictureAction()
    {
        return $this->doCategoryAction(function (
            $currentCategory,
            $currentCar,
            $isOther,
            $path,
            $breadcrumbs
        ) {

            $filter = [
                'item'   => [
                    'ancestor_or_self' => $currentCar ? $currentCar['id'] : $currentCategory['id']
                ],
                'status' => Picture::STATUS_ACCEPTED,
                'order'  => 'resolution_desc'
            ];

            $pictureFilter = $filter;
            $pictureFilter['identity'] = (string)$this->params('picture_id');

            $picture = $this->picture->getRow($pictureFilter);

            if (! $picture) {
                return $this->notFoundAction();
            }

            return [
                'breadcrumbs' => $breadcrumbs,
                'picture'     => array_replace(
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $this->pic()->picPageData($picture, $filter, []),
                    [
                        'galleryUrl' => $this->url()->fromRoute('categories', [
                            'action'           => 'category-picture-gallery',
                            'category_catname' => $currentCategory['catname'],
                            'other'            => $isOther,
                            'path'             => $path,
                            'picture_id'       => $picture['identity']
                        ])
                    ]
                )
            ];
        });
    }

    public function categoryPictureGalleryAction()
    {

        return $this->doCategoryAction(function ($currentCategory, $currentCar) {

            $filter = [
                'item'   => [
                    'ancestor_or_self' => $currentCar ? $currentCar['id'] : $currentCategory['id']
                ],
                'status' => Picture::STATUS_ACCEPTED,
                'order'  => 'resolution_desc'
            ];

            $pictureFilter = $filter;
            $pictureId = (string)$this->params('picture_id');
            $pictureFilter['identity'] = $pictureId;

            $picture = $this->picture->getRow($pictureFilter);

            if (! $picture) {
                return $this->notFoundAction();
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return new JsonModel($this->pic()->gallery2($filter, [
                'page'        => $this->params()->fromQuery('page'),
                'pictureId'   => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams'   => [
                    'action' => 'category-picture'
                ]
            ]));
        });
    }

    public function newcarsAction()
    {
        $category = $this->itemModel->getRow([
            'item_type_id' => Item::CATEGORY,
            'id'           => (int)$this->params('item_id')
        ]);
        if (! $category) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'item_type_id' => [Item::VEHICLE, Item::ENGINE],
            'order' => 'ip1.timestamp DESC',
            'parent' => [
                'item_type_id'     => Item::CATEGORY,
                'ancestor_or_self' => $category['id'],
                'linked_in_days'   => Categories::NEW_DAYS
            ],
            'limit' => 20
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->itemModel->getNameData($row, $language);
        }

        $viewModel = new ViewModel([
            'items' => $items
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
