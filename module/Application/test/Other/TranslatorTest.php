<?php

namespace ApplicationTest\Other;

use Application\Test\AbstractHttpControllerTestCase;

class TranslatorTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    /**
     * @dataProvider translationsProvider
     */
    public function testTranslatorWorks($text, $expected, $language)
    {
        $serviceManager = $this->getApplicationServiceLocator();
        $translator = $serviceManager->get('MvcTranslator');

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
