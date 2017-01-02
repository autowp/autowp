<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Model\DbTable\Attr;
use Application\Model\DbTable\Spec;
use Application\Model\DbTable\Vehicle;
use Application\Service\SpecificationsService;

class ChartController extends AbstractRestfulController
{
    private $parameters = [
        1, 2, 3, 47
    ];

    private $specs = [
        1, 29
    ];

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    public function __construct(SpecificationsService $specsService)
    {
        $this->specsService = $specsService;
    }

    public function yearsAction()
    {
        $attrTable = new Attr\Attribute();

        $params = [];
        foreach ($attrTable->find($this->parameters) as $row) {
            $params[] = [
                'name' => $this->translate($row->name),
                'id'   => $row->id
            ];
        }

        return [
            'parameters' => $params
        ];
    }

    private function specIds($db, $id)
    {
        $ids = $db->fetchCol(
            $db->select()
                ->from('spec', 'id')
                ->where('parent_id = ?', $id)
        );
        $result = [$id];
        foreach ($ids as $pid) {
            $result = array_merge($result, $this->specIds($db, $pid));
        }

        return array_merge($ids, $result);
    }

    public function yearsDataAction()
    {
        $id = $this->params()->fromQuery('id');

        if (! in_array($id, $this->parameters)) {
            return $this->notFoundAction();
        }

        $attrTable = new Attr\Attribute();
        $attrRow = $attrTable->find($id)->current();
        if (! $attrRow) {
            return $this->notFoundAction();
        }

        $dataTable = $this->specsService->getValueDataTable($attrRow->type_id);

        $dataTableName = $dataTable->info('name');

        $carTable = new Vehicle();
        $db = $carTable->getAdapter();
        $specTable = new Spec();

        $datasets = [];
        foreach ($this->specs as $specId) {
            $specRow = $specTable->find($specId)->current();
            $specIds = $this->specIds($specTable->getAdapter(), $specId);

            $pairs = $db->fetchPairs(
                $db->select()
                    ->from($dataTableName, ['year' => 'year(cars.begin_order_cache)', 'round(avg(value))'])
                    ->where($dataTableName . '.attribute_id = ?', $attrRow->id)
                    ->join('cars', $dataTableName . '.item_id = cars.id', null)
                    ->join('car_types_parents', 'cars.car_type_id = car_types_parents.id', null)
                    ->where('car_types_parents.parent_id = ?', 29)
                    ->where('cars.begin_order_cache')
                    ->where('cars.begin_order_cache < "2100-01-01 00:00:00"')
                    ->where('cars.spec_id in (?)', $specIds)
                    ->group('year')
                    ->order('year')
            );

            $datasets[] = [
                'title'  => $specRow->name,
                'pairs'  => $pairs,
            ];
        }

        $years = [];
        foreach ($datasets as $dataset) {
            $years = array_merge(array_keys($dataset['pairs']), $years);
        }
        $years = array_unique($years, SORT_NUMERIC);
        sort($years, SORT_NUMERIC);

        foreach ($datasets as &$dataset) {
            foreach ($years as $year) {
                if (! isset($dataset['pairs'][$year])) {
                    $dataset['pairs'][$year] = null;
                }
            }

            ksort($dataset['pairs'], SORT_NUMERIC);
        }
        unset($dataset);

        $result = [];
        foreach ($datasets as $dataset) {
            $result[] = [
                'name'   => $dataset['title'],
                'values' => array_values($dataset['pairs'])
            ];
        }

        return new JsonModel([
            'years'    => $years,
            'datasets' => $result
        ]);
    }
}
