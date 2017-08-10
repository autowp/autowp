<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Api\ItemController;
use Application\Controller\CarsController;

class CarsControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function createItem($params)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, $params);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $itemId = $parts[count($parts) - 1];

        return $itemId;
    }

    public function testSelectCarEngine()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/cars/select-car-engine/item_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('select-car-engine');

        // create engine
        $engineId = $this->createItem([
            'item_type_id' => 2,
            'name'         => 'Engine'
        ]);

        // select engine
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/cars/select-car-engine/item_id/1', Request::METHOD_POST, [
            'engine' => $engineId
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('select-car-engine');

        // cancel engine
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/cars/cancel-car-engine/item_id/1/tab/engine', Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('cancel-car-engine');

        // inherit engine
        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/cars/inherit-car-engine/item_id/1/tab/engine', Request::METHOD_POST);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('inherit-car-engine');
    }

    public function testCarsSpecificationsEditor()
    {
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/cars/car-specifications-editor/item_id/1', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('car-specifications-editor');

        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/cars/car-specifications-editor/item_id/1/form/car/tab/spec', Request::METHOD_POST, [
            'attr_16[attr_66]' => '11',
            'attr_16[attr_13]' => '3',
            'attr_16[attr_12][attr_67]' => '5',
            'attr_16[attr_12][attr_68]' => '2',
            'attr_16[attr_204]' => '85',
            'attr_14[attr_17][attr_1]' => '1234',
            'attr_14[attr_17][attr_2]' => '2345',
            'attr_14[attr_17][attr_140]' => '3456',
            'attr_14[attr_17][attr_3]' => '4567',
            'attr_14[attr_17][attr_141]' => '5678',
            'attr_14[attr_17][attr_203]' => '6789',
            'attr_14[attr_4]' => '2600',
            'attr_14[attr_18][attr_5]' => '2000',
            'attr_14[attr_18][attr_6]' => '2100',
            'attr_14[attr_167][attr_176]' => '20',
            'attr_14[attr_167][attr_7]' => '30',
            'attr_14[attr_167][attr_168]' => '40',
            'attr_14[attr_63][attr_64]' => '0.20',
            'attr_14[attr_63][attr_65]' => '0.40',
            'attr_70[attr_71]' => '1300',
            'attr_70[attr_72]' => '1400',
            'attr_70[attr_73]' => '1500',
            'attr_22[attr_100]' => 'HDi',
            'attr_22[attr_207]' => '106',
            'attr_22[attr_19][attr_20]' => '2',
            'attr_22[attr_19][attr_21]' => '4',
            'attr_22[attr_23]' => '25',
            'attr_22[attr_156]' => '69',
            'attr_22[attr_24][attr_25]' => '10',
            'attr_22[attr_24][attr_26]' => '8',
            'attr_22[attr_24][attr_27]' => '4',
            'attr_22[attr_24][attr_28]' => '84.5',
            'attr_22[attr_24][attr_29]' => '80.3',
            'attr_22[attr_24][attr_159]' => '50',
            'attr_22[attr_31]' => '2963',
            'attr_22[attr_32][attr_33]' => '200',
            'attr_22[attr_32][attr_34]' => '4000',
            'attr_22[attr_32][attr_35]' => '5000',
            'attr_22[attr_32][attr_171]' => '200',
            'attr_22[attr_32][attr_172]' => '200',
            'attr_22[attr_32][attr_173]' => '200',
            'attr_22[attr_32][attr_174]' => '200',
            'attr_22[attr_32][attr_177]' => '200',
            'attr_22[attr_32][attr_178]' => '200',
            'attr_22[attr_36][attr_37]' => '480',
            'attr_22[attr_36][attr_38]' => '4500',
            'attr_22[attr_36][attr_39]' => '6000',
            'attr_22[attr_30]' => '14',
            'attr_22[attr_98][]' => '39',
            'attr_22[attr_99]' => '47',
            'attr_22[attr_179]' => '81',
            'attr_22[attr_206]' => '100',
            'attr_40[attr_41]' => '17',
            'attr_40[attr_42][attr_139]' => 'Aisin',
            'attr_40[attr_42][attr_43]' => '50',
            'attr_40[attr_42][attr_44]' => '6',
            'attr_40[attr_83]' => 'Clutch',
            'attr_15[attr_208][attr_209]' => '121',
            'attr_15[attr_208][attr_210]' => '131',
            'attr_15[attr_208][attr_211][attr_213]' => '1',
            'attr_15[attr_208][attr_211][attr_214]' => '152',
            'attr_15[attr_208][attr_211][attr_215]' => '155',
            'attr_15[attr_208][attr_211][attr_216]' => '1',
            'attr_15[attr_208][attr_212]' => '1',
            'attr_15[attr_217][attr_218]' => '121',
            'attr_15[attr_217][attr_219]' => '131',
            'attr_15[attr_217][attr_220][attr_222]' => '1',
            'attr_15[attr_217][attr_220][attr_223]' => '152',
            'attr_15[attr_217][attr_220][attr_224]' => '155',
            'attr_15[attr_217][attr_220][attr_225]' => '1',
            'attr_15[attr_217][attr_221]' => '1',
            'attr_15[attr_10]' => 'Steering type',
            'attr_15[attr_8]' => 'Front suspension',
            'attr_15[attr_9]' => 'Rear suspension',
            'attr_181[attr_182]' => '12',
            'attr_46[attr_47]' => '300',
            'attr_46[attr_48]' => '10',
            'attr_46[attr_175]' => '11',
            'attr_46[attr_49]' => '20',
            'attr_46[attr_50]' => '30',
            'attr_46[attr_51]' => '40',
            'attr_46[attr_52]' => '80',
            'attr_46[attr_53]' => '1',
            'attr_46[attr_160]' => '8',
            'attr_46[attr_161]' => '25',
            'attr_54[attr_55]' => '30',
            'attr_54[attr_56]' => '20',
            'attr_54[attr_57][attr_58]' => '60',
            'attr_54[attr_57][attr_59]' => '90',
            'attr_54[attr_60][attr_61]' => '200',
            'attr_54[attr_60][attr_62]' => '300',
            'attr_54[attr_78][attr_183][attr_79]' => '8.8',
            'attr_54[attr_78][attr_183][attr_80]' => '10.5',
            'attr_54[attr_78][attr_183][attr_81]' => '8.8',
            'attr_54[attr_78][attr_184][attr_185]' => '8.8',
            'attr_54[attr_78][attr_184][attr_186]' => '10.5',
            'attr_54[attr_78][attr_184][attr_187]' => '8.8',
            'attr_54[attr_78][attr_184][attr_188]' => '10.5',
            'attr_54[attr_78][attr_189][attr_190]' => '8.8',
            'attr_54[attr_78][attr_189][attr_191]' => '10.5',
            'attr_54[attr_78][attr_192][attr_193]' => '8.8',
            'attr_54[attr_78][attr_192][attr_194]' => '10.5',
            'attr_54[attr_78][attr_199][attr_200]' => '8.8',
            'attr_54[attr_78][attr_199][attr_201]' => '10.5',
            'attr_54[attr_78][attr_199][attr_202]' => '11.6',
            'attr_54[attr_138]' => '1',
            'attr_54[attr_158]' => '300',
            'attr_54[attr_205]' => '800',
            'attr_54[attr_195][attr_11]' => '12',
            'attr_54[attr_195][attr_196]' => '11.5',
            'attr_54[attr_195][attr_197]' => '12.5',
            'attr_54[attr_198]' => '3',
            'attr_74[attr_77]' => '0',
            'attr_74[attr_142][attr_75]' => 'Front breakes',
            'attr_74[attr_142][attr_144]' => '58',
            'attr_74[attr_142][attr_146]' => '130',
            'attr_74[attr_142][attr_148]' => '30',
            'attr_74[attr_142][attr_150]' => '62',
            'attr_74[attr_142][attr_152]' => '1',
            'attr_74[attr_142][attr_153]' => '1',
            'attr_74[attr_143][attr_76]' => 'Rear breakes',
            'attr_74[attr_143][attr_145]' => '58',
            'attr_74[attr_143][attr_147]' => '130',
            'attr_74[attr_143][attr_149]' => '30',
            'attr_74[attr_143][attr_151]' => '62',
            'attr_74[attr_143][attr_154]' => '1',
            'attr_74[attr_143][attr_155]' => '1',
            'attr_82' => '330',
            'attr_157' => '74',
            'attr_84[attr_164]' => 'GALAXY',
            'attr_84[attr_165]' => '79',
            'attr_84[attr_85][attr_87]' => '235',
            'attr_84[attr_85][attr_90]' => '45',
            'attr_84[attr_85][attr_88]' => '18',
            'attr_84[attr_85][attr_89]' => '7',
            'attr_84[attr_85][attr_162]' => '20',
            'attr_84[attr_86][attr_91]' => '235',
            'attr_84[attr_86][attr_94]' => '45',
            'attr_84[attr_86][attr_92]' => '18',
            'attr_84[attr_86][attr_93]' => '7',
            'attr_84[attr_86][attr_163]' => '20',
            'attr_170' => 'Tyota Motors Mitischi',
        ]);

        $this->assertResponseStatusCode(302);
        $this->assertModuleName('application');
        $this->assertControllerName(CarsController::class);
        $this->assertMatchedRouteName('cars/params');
        $this->assertActionName('car-specifications-editor');
    }
}
