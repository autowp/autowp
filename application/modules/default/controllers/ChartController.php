<?php

class ChartController extends Zend_Controller_Action
{
    private $_parameters = array(
        1, 2, 3, 47
    );

    private $_specs = array(
        1, 29
    );

    public function yearsAction()
    {
        $attrTable = new Attrs_Attributes();

        $params = [];
        foreach ($attrTable->find($this->_parameters) as $row) {
            $params[] = array(
                'name' => $row->name,
                'id'   => $row->id
            );
        }

        $this->view->assign(array(
            'parameters' => $params
        ));
    }

    private function _specIds($db, $id)
    {
        $ids = $db->fetchCol(
            $db->select()
                ->from('spec', 'id')
                ->where('parent_id = ?', $id)
        );
        $result = [$id];
        foreach ($ids as $pid) {
            $result = array_merge($result, $this->_specIds($db, $pid));
        }

        return array_merge($ids, $result);
    }

    public function yearsDataAction()
    {
        $id = $this->getParam('id');

        if (!in_array($id, $this->_parameters)) {
            return $this->_forward('notfound', 'error');
        }

        $attrTable = new Attrs_Attributes();
        $attrRow = $attrTable->find($id)->current();
        if (!$attrRow) {
            return $this->_forward('notfound', 'error');
        }

        $specService = new Application_Service_Specifications();
        $dataTable = $specService->getValueDataTable($attrRow->type_id);

        $dataTableName = $dataTable->info('name');

        $carTable = new Cars();
        $db = $carTable->getAdapter();
        $specTable = new Spec();

        $datasets = [];
        foreach ($this->_specs as $specId) {

            $specRow = $specTable->find($specId)->current();
            $specIds = $this->_specIds($specTable->getAdapter(), $specId);

            $pairs = $db->fetchPairs(
                $db->select()
                    ->from($dataTableName, ['year' => 'year(cars.begin_order_cache)', 'round(avg(value))'])
                    ->where($dataTableName . '.item_type_id = ?', 1)
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

            $datasets[] = array(
                'title'  => $specRow->name,
                'pairs'  => $pairs,
            );
        }

        $years = [];
        foreach ($datasets as $dataset) {
            $years = array_merge(array_keys($dataset['pairs']), $years);
        }
        $years = array_unique($years, SORT_NUMERIC);
        sort($years, SORT_NUMERIC);

        foreach ($datasets as &$dataset) {
            foreach ($years as $year) {
                if (!isset($dataset['pairs'][$year])) {
                    $dataset['pairs'][$year] = null;
                }
            }

            ksort($dataset['pairs'], SORT_NUMERIC);
        }
        unset($dataset);

        $result = [];
        foreach ($datasets as $dataset) {
            $result[] = array(
                'name'   => $dataset['title'],
                'values' => array_values($dataset['pairs'])
            );
        }

        return $this->_helper->json(array(
            'years'    => $years,
            'datasets' => $result
        ));
    }
}