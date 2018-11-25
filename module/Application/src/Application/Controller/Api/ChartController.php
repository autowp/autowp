<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

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

    /**
     * @var TableGateway
     */
    private $specTable;

    /**
     * @var TableGateway
     */
    private $attributeTable;

    public function __construct(
        SpecificationsService $specsService,
        TableGateway $specTable,
        TableGateway $attributeTable
    ) {
        $this->specsService = $specsService;
        $this->specTable = $specTable;
        $this->attributeTable = $attributeTable;
    }

    public function parametersAction()
    {
        $rows = $this->attributeTable->select([new Sql\Predicate\In('id', $this->parameters)]);

        $params = [];
        foreach ($rows as $row) {
            $params[] = [
                'name' => $this->translate($row['name']),
                'id'   => (int)$row['id']
            ];
        }

        return new JsonModel([
            'parameters' => $params
        ]);
    }

    private function specIds(int $id)
    {
        $select = new Sql\Select($this->specTable->getTable());
        $select->columns(['id'])
            ->where(['parent_id' => $id]);

        $ids = [];
        foreach ($this->specTable->selectWith($select) as $row) {
            $ids[] = (int)$row['id'];
        }

        $result = [$id];
        foreach ($ids as $pid) {
            $result = array_merge($result, $this->specIds($pid));
        }

        return array_merge($ids, $result);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function dataAction()
    {
        $id = (int)$this->params()->fromQuery('id');

        if (! in_array($id, $this->parameters)) {
            return $this->notFoundAction();
        }

        $attrRow = $this->attributeTable->select(['id' => $id])->current();
        if (! $attrRow) {
            return $this->notFoundAction();
        }

        $dataTable = $this->specsService->getValueDataTable($attrRow['type_id']);

        $dataTableName = $dataTable->getTable();

        $datasets = [];
        foreach ($this->specs as $specId) {
            $specRow = $this->specTable->select(['id' => $specId])->current();
            $specIds = $this->specIds($specId);

            $select = new Sql\Select($dataTable->getTable());
            $select->columns([
                    'year'  => new Sql\Expression('year(item.begin_order_cache)'),
                    'value' => new Sql\Expression('round(avg(value))')
                ])
                ->join('item', $dataTableName . '.item_id = item.id', [])
                ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', [])
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', [])
                ->where([
                    $dataTableName . '.attribute_id' => $attrRow['id'],
                    'car_types_parents.parent_id' => 29,
                    'item.begin_order_cache',
                    'item.begin_order_cache < "2100-01-01 00:00:00"',
                    new Sql\Predicate\In('item.spec_id', $specIds)
                ])
                ->group('year')
                ->order('year');

            $pairs = [];
            foreach ($dataTable->selectWith($select) as $row) {
                $pairs[$row['year']] = $row['value'];
            }

            $datasets[] = [
                'title'  => $specRow['name'],
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
