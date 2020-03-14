<?php

namespace ApplicationTest\Other;

use Application\Test\AbstractHttpControllerTestCase;
use Autowp\User\View\Helper\User;
use Exception;
use Laminas\View\HelperPluginManager;

class UserTextRendererTest extends AbstractHttpControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    /**
     * @dataProvider hyperlinksProvider
     * @throws Exception
     */
    public function testHyperlinks(string $text, string $expected)
    {
        $serviceManager = $this->getApplicationServiceLocator();
        /** @var HelperPluginManager $viewHelperManager */
        $viewHelperManager = $serviceManager->get('ViewHelperManager');
        /** @var User $helper */
        $helper = $viewHelperManager->get('userText');

        $result = $helper($text);
        $this->assertEquals($expected, $result->__toString());
    }

    public static function usersProvider(): array
    {
        return [
            [
                'http://www.autowp.ru/users/user1',
                '<i class="fa fa-user" aria-hidden="true"></i>&#xa0;<a href="&#x2F;users&#x2F;user1">tester</a>',
            ],
            [
                'http://www.autowp.ru/users/user1',
                '<i class="fa fa-user" aria-hidden="true"></i>&#xa0;<a href="&#x2F;users&#x2F;user1">tester</a>',
            ],
            [
                'http://www.autowp.ru/users/user9999999999/',
                '<a href="http&#x3A;&#x2F;&#x2F;www.autowp.ru&#x2F;users&#x2F;user9999999999&#x2F;">'
                . 'http://www.autowp.ru/users/user9999999999/</a>',
            ],
            [
                'http://www.autowp.ru/users/identity',
                '<i class="fa fa-user" aria-hidden="true"></i>&#xa0;<a href="&#x2F;users&#x2F;identity">'
                . 'tester2</a>',
            ],
            [
                'http://www.autowp.ru/users/identity',
                '<i class="fa fa-user" aria-hidden="true"></i>&#xa0;<a href="&#x2F;users&#x2F;identity">'
                . 'tester2</a>',
            ],
        ];
    }

    /**
     * @dataProvider usersProvider
     * @throws Exception
     */
    public function testUsers(string $text, string $expected)
    {
        $serviceManager = $this->getApplicationServiceLocator();
        /** @var HelperPluginManager $viewHelperManager */
        $viewHelperManager = $serviceManager->get('ViewHelperManager');
        /** @var User $helper */
        $helper = $viewHelperManager->get('userText');

        $result = $helper($text);
        $this->assertStringContainsString($expected, $result->__toString());
    }

    public static function hyperlinksProvider(): array
    {
        return [
            ['just.test', 'just.test'],
            ["Multiline\ntest", "Multiline<br />test"],
            ["Test with &ampersand", "Test with &amp;ampersand"],
            ["Test with \"quote", "Test with &quot;quote"],
            [
                "Test with http://example.com/",
                'Test with <a href="http&#x3A;&#x2F;&#x2F;example.com&#x2F;">http://example.com/</a>',
            ],
            [
                "Test with www.example.com/path link",
                'Test with <a href="http&#x3A;&#x2F;&#x2F;www.example.com&#x2F;path">http://www.example.com/path</a>'
                . ' link',
            ],
            [
                "Test with https://example.com/",
                'Test with <a href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;">https://example.com/</a>',
            ],
            [
                "https://example.com/#hash",
                '<a href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;&#x23;hash">https://example.com/#hash</a>',
            ],
            [
                "https://example.com/?param=test#hash",
                '<a href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;&#x3F;param&#x3D;test&#x23;hash">'
                . 'https://example.com/?param=test#hash</a>',
            ],
            [
                "1. https://example.com/ 2. www.google.com",
                '1. <a href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;">https://example.com/</a> '
                . '2. <a href="http&#x3A;&#x2F;&#x2F;www.google.com">http://www.google.com</a>',
            ],
            [
                '<a href="https://example.com/">https://example.com/</a>',
                '&lt;a href=&quot;<a href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;">https://example.com/</a>'
                . '&quot;&gt;<a href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;">https://example.com/</a>&lt;/a&gt;',
            ],
        ];
    }
}
