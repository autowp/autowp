<?php

namespace ApplicationTest\Other;

use Zend\Test\PHPUnit\Controller\AbstractControllerTestCase;

/**
 * @group Autowp_UserText
 */
class TranslatorTest extends AbstractControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/../_files/application.config.php');

        parent::setUp();
    }

    /**
     * @dataProvider translationsProvider
     */
    public function testTranslatorWorks($text, $expected, $language)
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $translator = $serviceManager->get('MvcTranslator');

        $config = $serviceManager->get('Config');

        $result = $translator->translate($text, 'default', $language);

        $this->assertEquals($expected, $result);
    }

    public static function translationsProvider()
    {
        return [
            ['test', 'test is ok', 'en'],
            ['test', 'test est ok', 'fr'],
            ['test', 'тест прошел успешно', 'ru'],
            ['test', '测试是确定', 'zh'],
        ];
    }
}