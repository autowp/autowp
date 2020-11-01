<?php

namespace Application\Controller\Api;

use Application\Service\SpecificationsService;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function Autowp\Commons\currentFromResultSetInterface;
use function in_array;
use function ksort;
use function sort;

use const SORT_NUMERIC;

/**
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ChartController extends AbstractRestfulController
{
    private array $parameters = [
        1,
        2,
        3,
        47,
    ];

    private array $specs = [
        1,
        29,
    ];

    private SpecificationsService $specsService;

    private TableGateway $specTable;

    private TableGateway $attributeTable;

    public function __construct(
        SpecificationsService $specsService,
        TableGateway $specTable,
        TableGateway $attributeTable
    ) {
        $this->specsService   = $specsService;
        $this->specTable      = $specTable;
        $this->attributeTable = $attributeTable;
    }

    public function parametersAction(): JsonModel
    {
        $rows = $this->attributeTable->select([new Sql\Predicate\In('id', $this->parameters)]);

        $params = [];
        foreach ($rows as $row) {
            $params[] = [
                'name' => $this->translate($row['name']),
                'id'   => (int) $row['id'],
            ];
        }

        return new JsonModel([
            'parameters' => $params,
        ]);
    }

    private function specIds(int $id): array
    {
        $select = new Sql\Select($this->specTable->getTable());
        $select->columns(['id'])
            ->where(['parent_id' => $id]);

        $ids = [];
        foreach ($this->specTable->selectWith($select) as $row) {
            $ids[] = (int) $row['id'];
        }

        $result = [$id];
        foreach ($ids as $pid) {
            $result = array_merge($result, $this->specIds($pid));
        }

        return array_merge($ids, $result);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @return ViewModel|ResponseInterface|array
     */
    public function dataAction()
    {
        $id = (int) $this->params()->fromQuery('id');

        if (! in_array($id, $this->parameters)) {
            return $this->notFoundAction();
        }

        $attrRow = currentFromResultSetInterface($this->attributeTable->select(['id' => $id]));
        if (! $attrRow) {
            return $this->notFoundAction();
        }

        $dataTable = $this->specsService->getValueDataTable($attrRow['type_id']);

        $dataTableName = $dataTable->getTable();

        $datasets = [];
        foreach ($this->specs as $specId) {
            $specRow = currentFromResultSetInterface($this->specTable->select(['id' => $specId]));
            $specIds = $this->specIds($specId);

            $select = new Sql\Select($dataTable->getTable());
            $select->columns([
                'year'  => new Sql\Expression('year(item.begin_order_cache)'),
                'value' => new Sql\Expression('round(avg(value))'),
            ])
                ->join('item', $dataTableName . '.item_id = item.id', [])
                ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', [])
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', [])
                ->where([
                    $dataTableName . '.attribute_id' => $attrRow['id'],
                    'car_types_parents.parent_id'    => 29,
                    'item.begin_order_cache',
                    'item.begin_order_cache < "2100-01-01 00:00:00"',
                    new Sql\Predicate\In('item.spec_id', $specIds),
                ])
                ->group('year')
                ->order('year');

            $pairs = [];
            foreach ($dataTable->selectWith($select) as $row) {
                $pairs[$row['year']] = $row['value'];
            }

            $datasets[] = [
                'title' => $specRow['name'],
                'pairs' => $pairs,
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
                'values' => array_values($dataset['pairs']),
            ];
        }

        return new JsonModel([
            'years'    => $years,
            'datasets' => $result,
        ]);
    }
}
