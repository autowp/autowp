<?php

namespace Application\Controller\Api;

use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Exception;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\Sql;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_merge;
use function array_values;
use function count;
use function strnatcasecmp;
use function usort;

/**
 * @method string language()
 */
class BrandsController extends AbstractActionController
{
    private StorageInterface $cache;

    private Brand $brand;

    private VehicleType $vehicleType;

    private Item $itemModel;

    private Picture $picture;

    private TranslatorInterface $translator;

    public function __construct(
        StorageInterface $cache,
        Brand $brand,
        VehicleType $vehicleType,
        Item $itemModel,
        Picture $picture,
        TranslatorInterface $translator
    ) {
        $this->cache       = $cache;
        $this->brand       = $brand;
        $this->vehicleType = $vehicleType;
        $this->itemModel   = $itemModel;
        $this->picture     = $picture;
        $this->translator  = $translator;
    }

    public function indexAction(): JsonModel
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $isHttps = (bool) $this->getRequest()->getServer('HTTPS');

        $language = $this->language();

        $cacheKey = 'brands_list_46_' . $language . '_' . ($isHttps ? 'HTTPS' : 'HTTP');

        $items = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $items = $this->brand->getFullBrandsList($language);
            $this->cache->setItem($cacheKey, $items);
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @throws Exception
     * @return ViewModel|ResponseInterface|array
     */
    public function sectionsAction()
    {
        $language = $this->language();

        $rows  = $this->itemModel->getRows([
            'id'           => (int) $this->params('id'),
            'item_type_id' => Item::BRAND,
            'columns'      => ['id', 'catname'],
        ]);
        $brand = count($rows) ? $rows[0] : null;
        if (! $brand) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->brandSections($language, $brand['id'], $brand['catname']));
    }

    /**
     * @throws Exception
     */
    private function brandSections(
        string $language,
        int $brandId,
        string $brandCatname
    ): array {
        // create groups array
        $sections = $this->carSections($language, $brandId, $brandCatname, true);

        $sections = array_merge(
            $sections,
            [
                [
                    'name'       => 'Other',
                    'routerLink' => null,
                    'groups'     => $this->otherGroups(
                        $brandId,
                        $brandCatname,
                        true
                    ),
                ],
            ]
        );

        return $sections;
    }

    /**
     * @throws Exception
     */
    private function otherGroups(
        int $brandId,
        string $brandCatname,
        bool $conceptsSeparately
    ): array {
        $groups = [];

        if ($conceptsSeparately) {
            // concepts
            $hasConcepts = $this->itemModel->isExists([
                'ancestor'   => $brandId,
                'is_concept' => true,
            ]);

            if ($hasConcepts) {
                $groups['concepts'] = [
                    'routerLink' => ['/', $brandCatname, 'concepts'],
                    'name'       => $this->translator->translate('concepts and prototypes'),
                ];
            }
        }

        // logotypes
        $logoPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'          => $brandId,
                'perspective' => 22,
            ],
        ]);

        if ($logoPicturesCount > 0) {
            $groups['logo'] = [
                'routerLink' => ['/', $brandCatname, 'logotypes'],
                'name'       => $this->translator->translate('logotypes'),
                'count'      => $logoPicturesCount,
            ];
        }

        // mixed
        $mixedPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'          => $brandId,
                'perspective' => 25,
            ],
        ]);
        if ($mixedPicturesCount > 0) {
            $groups['mixed'] = [
                'routerLink' => ['/', $brandCatname, 'mixed'],
                'name'       => $this->translator->translate('mixed'),
                'count'      => $mixedPicturesCount,
            ];
        }

        // unsorted
        $unsortedPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'                  => $brandId,
                'perspective_exclude' => [22, 25],
            ],
        ]);
        if ($unsortedPicturesCount > 0) {
            $groups['unsorted'] = [
                'routerLink' => ['/', $brandCatname, 'other'],
                'name'       => $this->translator->translate('unsorted'),
                'count'      => $unsortedPicturesCount,
            ];
        }

        return array_values($groups);
    }

    private function carSections(
        string $language,
        int $brandId,
        string $brandCatname,
        bool $conceptsSeparatly
    ): array {
        $sectionsPresets = [
            'other'   => [
                'name'         => null,
                'car_type_id'  => null,
                'item_type_id' => Item::VEHICLE,
            ],
            'moto'    => [
                'name'         => 'catalogue/section/moto',
                'car_type_id'  => 43,
                'item_type_id' => Item::VEHICLE,
            ],
            'bus'     => [
                'name'         => 'catalogue/section/buses',
                'car_type_id'  => 19,
                'item_type_id' => Item::VEHICLE,
            ],
            'truck'   => [
                'name'         => 'catalogue/section/trucks',
                'car_type_id'  => 17,
                'item_type_id' => Item::VEHICLE,
            ],
            'tractor' => [
                'name'         => 'catalogue/section/tractors',
                'car_type_id'  => 44,
                'item_type_id' => Item::VEHICLE,
            ],
            'engine'  => [
                'name'         => 'catalogue/section/engines',
                'car_type_id'  => null,
                'item_type_id' => Item::ENGINE,
                'router_link'  => ['/', $brandCatname, 'engines'],
            ],
        ];

        $sections = [];
        foreach ($sectionsPresets as $sectionsPreset) {
            $sectionGroups = $this->carSectionGroups(
                $language,
                $brandId,
                $brandCatname,
                $sectionsPreset,
                $conceptsSeparatly
            );

            usort($sectionGroups, function ($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });

            $sections[] = [
                'name'       => $sectionsPreset['name'],
                'routerLink' => $sectionsPreset['router_link'] ?? null,
                'groups'     => $sectionGroups,
            ];
        }

        return $sections;
    }

    private function carSectionGroups(
        string $language,
        int $brandId,
        string $brandCatname,
        array $section,
        bool $conceptsSeparatly
    ): array {
        if ($section['car_type_id']) {
            $select = $this->carSectionGroupsSelect(
                $brandId,
                $section['item_type_id'],
                $section['car_type_id'],
                null,
                $conceptsSeparatly
            );
            $rows   = $this->itemModel->getTable()->selectWith($select);
        } else {
            $rows   = [];
            $select = $this->carSectionGroupsSelect(
                $brandId,
                $section['item_type_id'],
                0,
                false,
                $conceptsSeparatly
            );
            foreach ($this->itemModel->getTable()->selectWith($select) as $row) {
                $rows[$row['item_id']] = $row;
            }
            $select = $this->carSectionGroupsSelect(
                $brandId,
                $section['item_type_id'],
                0,
                true,
                $conceptsSeparatly
            );
            foreach ($this->itemModel->getTable()->selectWith($select) as $row) {
                $rows[$row['item_id']] = $row;
            }
        }

        $groups = [];
        foreach ($rows as $brandItemRow) {
            $groups[] = [
                'item_id'    => $brandItemRow['item_id'],
                'routerLink' => ['/', $brandCatname, $brandItemRow['brand_item_catname']],
                'name'       => $this->itemModel->getName($brandItemRow['item_id'], $language),
            ];
        }

        return $groups;
    }

    private function carSectionGroupsSelect(
        int $brandId,
        int $itemTypeId,
        int $carTypeId,
        ?bool $nullType,
        bool $conceptsSeparately
    ): Sql\Select {
        $select = new Sql\Select($this->itemModel->getTable()->getTable());
        $select
            ->columns([
                'item_id'  => 'id',
                'car_name' => 'name',
            ])
            ->join('item_parent', 'item.id = item_parent.item_id', [
                'brand_item_catname' => 'catname',
                'brand_id'           => 'parent_id',
            ])
            ->where(['item_parent.parent_id' => $brandId])
            ->group('item.id');

        if ($conceptsSeparately) {
            $select->where(['NOT item.is_concept']);
        }

        if ($itemTypeId !== Item::VEHICLE) {
            $select->where(['item.item_type_id' => $itemTypeId]);

            return $select;
        }

        $select->where([
            new Sql\Predicate\In('item.item_type_id', [Item::VEHICLE, Item::BRAND]),
        ]);
        if ($carTypeId) {
            $select
                ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', [])
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', [])
                ->where(['car_types_parents.parent_id' => $carTypeId]);

            return $select;
        }

        if ($nullType) {
            $select
                ->join(
                    'vehicle_vehicle_type',
                    'item.id = vehicle_vehicle_type.vehicle_id',
                    [],
                    $select::JOIN_LEFT
                )
                ->where(['vehicle_vehicle_type.vehicle_id is null']);

            return $select;
        }

        $otherTypesIds = $this->vehicleType->getDescendantsAndSelfIds([43, 44, 17, 19]);

        $select->join(
            'vehicle_vehicle_type',
            'item.id = vehicle_vehicle_type.vehicle_id',
            []
        );

        if ($otherTypesIds) {
            $select->where([
                new Sql\Predicate\NotIn(
                    'vehicle_vehicle_type.vehicle_type_id',
                    $otherTypesIds
                ),
            ]);
        }

        return $select;
    }

    /**
     * @throws Exception
     */
    public function newItemsAction(): ViewModel
    {
        $brand = $this->itemModel->getRow([
            'item_type_id' => Item::BRAND,
            'id'           => (int) $this->params()->fromRoute('id'),
        ]);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $langName = $this->itemModel->getName($brand['id'], $language);

        $carList = $this->itemModel->getRows([
            'ancestor'        => $brand['id'],
            'created_in_days' => 7,
            'limit'           => 30,
            'order'           => 'item.add_datetime DESC',
        ]);

        $cars = [];
        foreach ($carList as $car) {
            $cars[] = $this->itemModel->getNameData($car, $language);
        }

        $viewModel = new ViewModel([
            'brand'   => $brand,
            'carList' => $cars,
            'name'    => $langName ? $langName : $brand['name'],
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
