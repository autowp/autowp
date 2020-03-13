<?php

namespace Application\Controller\Api;

use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Service\DayPictures;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * @method User user($user = null)
 * @method string language()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class InboxController extends AbstractRestfulController
{
    private Picture $picture;

    private Brand $brand;

    private InputFilter $inputFilter;

    public function __construct(Picture $picture, Brand $brand, InputFilter $inputFilter)
    {
        $this->picture     = $picture;
        $this->brand       = $brand;
        $this->inputFilter = $inputFilter;
    }

    /**
     * @throws Exception
     */
    private function getBrandControl(): array
    {
        $language = $this->language();

        $brands = $this->brand->getList([
            'language' => $language,
        ], function (Sql\Select $select) {
            $subSelect = new Sql\Select('item');
            $subSelect->columns(['id'])
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->join('pictures', 'picture_item.picture_id = pictures.id', [])
                ->where([
                    'item.item_type_id' => Item::BRAND,
                    'pictures.status'   => Picture::STATUS_INBOX,
                ]);

            $select->where([
                new Sql\Predicate\In('item.id', $subSelect),
            ]);
        });

        $brandOptions = [];
        foreach ($brands as $iBrand) {
            $brandOptions[] = [
                'id'   => (int) $iBrand['id'],
                'name' => $iBrand['name'],
            ];
        }

        return $brandOptions;
    }

    /**
     * @return array|JsonModel|ApiProblemResponse
     * @throws Exception
     */
    public function indexAction()
    {
        $this->inputFilter->setData($this->params()->fromQuery());

        if (! $this->inputFilter->isValid()) {
            return $this->inputFilterResponse($this->inputFilter);
        }

        $values = $this->inputFilter->getValues();

        $language = $this->language();

        $brand = null;
        if ($values['brand_id']) {
            $brand = $this->brand->getBrandById($values['brand_id'], $language);
        }

        $select = $this->picture->getTable()->getSql()->select()
            ->where(['pictures.status' => Picture::STATUS_INBOX]);
        if ($brand) {
            $select
                ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                ->where(['item_parent_cache.parent_id' => $brand['id']])
                ->group('pictures.id');
        }

        $service = new DayPictures([
            'picture'     => $this->picture,
            'timezone'    => $this->user()->timezone(), // @phan-suppress-current-line PhanUndeclaredMethod
            'dbTimezone'  => MYSQL_TIMEZONE,
            'select'      => $select,
            'orderColumn' => 'add_date',
            'currentDate' => $values['date'],
        ]);

        if (! $service->haveCurrentDate() || ! $service->haveCurrentDayPictures()) {
            $lastDate = $service->getLastDateStr();

            if (! $lastDate) {
                return $this->notFoundAction();
            }

            $service->setCurrentDate($lastDate);
        }

        $prevDate    = $service->getPrevDate();
        $currentDate = $service->getCurrentDate();
        $nextDate    = $service->getNextDate();

        return new JsonModel([
            'brands'  => $this->getBrandControl(),
            'prev'    => [
                'date'  => $prevDate ? $prevDate->format('Y-m-d') : null,
                'count' => $service->getPrevDateCount(),
            ],
            'current' => [
                'date'  => $currentDate ? $currentDate->format('Y-m-d') : null,
                'count' => $service->getCurrentDateCount(),
            ],
            'next'    => [
                'date'  => $nextDate ? $nextDate->format('Y-m-d') : null,
                'count' => $service->getNextDateCount(),
            ],
        ]);
    }
}
