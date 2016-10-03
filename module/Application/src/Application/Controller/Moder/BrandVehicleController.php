<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable;

use Autowp\Filter\Filename\Safe;

use Zend_Db_Expr;

class BrandVehicleController extends AbstractActionController
{
    private $languages = ['ru', 'en', 'fr', 'zh'];

    /**
     * @var BrandTable
     */
    private $brandTable;

    /**
     * @param Vehicle\Row $car
     * @return string
     */
    private function vehicleModerUrl(DbTable\Vehicle\Row $car, $full = false, $tab = null, $uri = null)
    {
        return $this->url()->fromRoute('moder/cars/params', [
            'action' => 'car',
            'car_id' => $car->id,
            'tab'    => $tab
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    /**
     * @return BrandTable
     */
    private function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new DbTable\Brand();
    }

    public function itemAction()
    {
        if (!$this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $brandTable = $this->getBrandTable();
        $brandVehicleTable = new DbTable\BrandCar();
        $brandVehicleLangaugeTable = new DbTable\Brand\VehicleLanguage();
        $vehicleTable = $this->catalogue()->getCarTable();

        $brandCarRow = $brandVehicleTable->fetchRow([
            'brand_id = ?' => $this->params('brand_id'),
            'car_id = ?'   => $this->params('vehicle_id')
        ]);

        if (!$brandCarRow) {
            return $this->notFoundAction();
        }

        $brandRow = $brandTable->find($brandCarRow->brand_id)->current();
        $vehicleRow = $vehicleTable->find($brandCarRow->car_id)->current();
        if (!$brandRow || !$vehicleRow) {
            return $this->notFoundAction();
        }

        $form = new \Application\Form\Moder\BrandVehicle(null, [
            'languages' => $this->languages,
            'brandId'   => $brandRow->id,
            'vehicleId' => $vehicleRow->id
        ]);

        $values = [
            'catname' => $brandCarRow->catname,
        ];

        $bvlRows = $brandVehicleLangaugeTable->fetchAll([
            'vehicle_id = ?' => $vehicleRow->id,
            'brand_id = ?'   => $brandRow->id
        ]);
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow->language] = [
                'name' => $bvlRow->name
            ];
        }

        $form->populateValues($values);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $brandCarRow->setFromArray([
                    'catname' => $values['catname'],
                    'type'    => $values['type'],
                ]);
                $brandCarRow->save();

                //var_dump($values['catname'], $filter->filter($values['catname']), $brandCarRow->catname); exit;

                $this->setBrandVehicleLanguages($brandRow, $vehicleRow, $values);

                return $this->redirect()->toRoute(null, [], [], true);
            }
        }

        return [
            'brand'   => $brandRow,
            'vehicle' => $vehicleRow,
            'form'    => $form
        ];
    }

    private function setBrandVehicleLanguages(DbTable\BrandRow $brandRow, DbTable\Vehicle\Row $vehicleRow, array $values)
    {
        $brandVehicleLangaugeTable = new DbTable\Brand\VehicleLanguage();

        foreach ($this->languages as $language) {
            $bvlRow = $brandVehicleLangaugeTable->fetchRow([
                'vehicle_id = ?' => $vehicleRow->id,
                'brand_id = ?'   => $brandRow->id,
                'language = ?'   => $language
            ]);
            if (!$bvlRow) {
                $bvlRow = $brandVehicleLangaugeTable->createRow([
                    'vehicle_id' => $vehicleRow->id,
                    'brand_id'   => $brandRow->id,
                    'language'   => $language
                ]);
            }

            $bvlRow->setFromArray([
                'name' => $values[$language]['name']
            ]);
            $bvlRow->save();
        }
    }

    private function getBrandAliases(DbTable\BrandRow $brandRow)
    {
        $aliases = [$brandRow['caption']];

        $brandAliasTable = new DbTable\BrandAlias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'brand_id = ?' => $brandRow['id']
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }

        $brandLangTable = new DbTable\BrandLanguage();
        $brandLangRows = $brandLangTable->fetchAll([
            'brand_id = ?' => $brandRow['id']
        ]);
        foreach ($brandLangRows as $brandLangRow) {
            $aliases[] = $brandLangRow->name;
        }

        usort($aliases, function($a, $b) {
            $la = mb_strlen($a);
            $lb = mb_strlen($b);

            if ($la == $lb) {
                return 0;
            }
            return ($la > $lb) ? -1 : 1;
        });

        return $aliases;
    }

    private function getVehicleName(DbTable\Vehicle\Row $vehicleRow, $language)
    {
        $languageTable = new DbTable\Vehicle\Language;

        $db = $languageTable->getAdapter();

        $order = new Zend_Db_Expr($db->quoteInto('language = ? DESC', $language));

        $languageRow = $languageTable->fetchRow([
            'car_id = ?' => $vehicleRow->id
        ], $order);

        return $languageRow ? $languageRow->name : $vehicleRow->caption;
    }

    private function extractName(DbTable\BrandRow $brandRow, DbTable\Vehicle\Row $vehicleRow, $language)
    {
        $carLanguageTable = new DbTable\Vehicle\Language();

        $vehicleName = $this->getVehicleName($vehicleRow, $language);
        $aliases = $this->getBrandAliases($brandRow);

        $name = $vehicleName;
        foreach ($aliases as $alias) {
            $name = str_ireplace('by The ' . $alias . ' Company', '', $name);
            $name = str_ireplace('by '.$alias, '', $name);
            $name = str_ireplace('di '.$alias, '', $name);
            $name = str_ireplace('par '.$alias, '', $name);
            $name = str_ireplace($alias.'-', '', $name);
            $name = str_ireplace('-'.$alias, '', $name);

            $name = preg_replace('/\b'.preg_quote($alias, '/').'\b/iu', '', $name);
        }

        $name = trim(preg_replace("|[[:space:]]+|", ' ', $name));
        $name = ltrim($name, '/');
        if (!$name) {
            $name = $vehicleName;
        }

        return $name;
    }

    public function addAction()
    {
        if (!$this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $brandTable = $this->getBrandTable();
        $brandVehicleTable = new DbTable\BrandCar();
        $brandVehicleLangaugeTable = new DbTable\Brand\VehicleLanguage();
        $vehicleTable = $this->catalogue()->getCarTable();

        $brandRow = $brandTable->find($this->params('brand_id'))->current();
        $vehicleRow = $vehicleTable->find($this->params('vehicle_id'))->current();
        if (!$brandRow || !$vehicleRow) {
            return $this->notFoundAction();
        }

        $brandCarRow = $brandVehicleTable->fetchRow([
            'brand_id = ?' => $brandRow->id,
            'car_id = ?'   => $vehicleRow->id
        ]);

        if ($brandCarRow) {
            return $this->forbiddenAction();
        }

        $filter = new Safe();
        $catnameTemplate = $filter->filter($this->extractName($brandRow, $vehicleRow, 'en'));

        $i = 0;
        do {

            $catname = $catnameTemplate . ($i ? '_' . $i : '');

            $exists = (bool)$brandVehicleTable->fetchRow([
                'brand_id = ?' => $brandRow->id,
                'catname = ?'  => $catname
            ]);

            $i++;

        } while ($exists);

        $brandCarRow = $brandVehicleTable->createRow([
            'brand_id' => $brandRow->id,
            'car_id'   => $vehicleRow->id,
            'type'     => DbTable\BrandCar::TYPE_DEFAULT,
            'catname'  => $catname ? $catname : 'vehicle' . $vehicleRow->id
        ]);
        $brandCarRow->save();

        $values = [];
        foreach ($this->languages as $language) {
            $values[$language] = [
                'name' => $this->extractName($brandRow, $vehicleRow, $language)
            ];
        }

        $this->setBrandVehicleLanguages($brandRow, $vehicleRow, $values);


        $user = $this->user()->get();
        $ucsTable = new DbTable\User\CarSubscribe();
        $ucsTable->subscribe($user, $vehicleRow);

        $brandRow->refreshPicturesCount();

        $message = sprintf(
            'Автомобиль %s добавлен к бренду %s',
            htmlspecialchars($vehicleRow->getFullName('en')),
            $brandRow->caption
        );
        $this->log($message, [$brandRow, $vehicleRow]);

        return $this->redirectToCar($vehicleRow, 'catalogue');
    }

    public function deleteAction()
    {
        if (!$this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $brandTable = $this->getBrandTable();
        $vehicleTable = $this->catalogue()->getCarTable();
        $brandVehicleTable = new DbTable\BrandCar();
        $brandVehicleLangaugeTable = new DbTable\Brand\VehicleLanguage();

        $brandRow = $brandTable->find($this->params('brand_id'))->current();
        $vehicleRow = $vehicleTable->find($this->params('vehicle_id'))->current();

        if (!$brandRow || !$vehicleRow) {
            return $this->notFoundAction();
        }

        $brandVehicleLangaugeTable->delete([
            'brand_id = ?'   => $brandRow->id,
            'vehicle_id = ?' => $vehicleRow->id
        ]);

        $brandVehicleTable->delete([
            'brand_id = ?' => $brandRow->id,
            'car_id = ?'   => $vehicleRow->id
        ]);

        $user = $this->user()->get();
        $ucsTable = new DbTable\User\CarSubscribe();
        $ucsTable->subscribe($user, $vehicleRow);

        $brandRow->refreshPicturesCount();

        $message = sprintf(
            'Автомобиль %s отсоединен от бренда %s',
            htmlspecialchars($vehicleRow->getFullName('en')),
            $brandRow->caption
        );
        $this->log($message, [$brandRow, $vehicleRow]);

        return $this->redirectToCar($vehicleRow, 'catalogue');
    }

    /**
     * @param Vehicle\Row $car
     * @return void
     */
    private function redirectToCar(DbTable\Vehicle\Row $vehicleRow, $tab = null)
    {
        return $this->redirect()->toUrl($this->vehicleModerUrl($vehicleRow, true, $tab));
    }
}
