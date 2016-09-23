<?php

namespace AutowpTest;

use Autowp\UserText\Renderer;

use Zend_Controller_Front;

/**
 * @group Autowp_UserText
 */
class UserTextRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider hyperlinksProvider
     */
    public function testHyperlinks($text, $expected)
    {
        $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
        $view = $bootstrap->getResource('View');
        
        $renderer = new Renderer($view);
        $result = $renderer->render($text);
        $this->assertEquals($expected, $result);
    }
    
    /**
     * @dataProvider usersProvider
     */
    public function testUsers($text, $expected)
    {
        $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
        $view = $bootstrap->getResource('View');
        
        $renderer = new Renderer($view);
        $result = $renderer->render($text);
        $this->assertEquals($expected, $result);
    }
    
    /*public function testPictures()
    {
        $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
        $view = $bootstrap->getResource('View');
        
        $text = 'https://fr.wheelsage.org/subaru/impreza/ii/16899/pictures/225671/';
    
        $renderer = new Renderer($view);
        $result = $renderer->render($text);
        $this->assertContains(
                '/picture/225671', 
                $result
        );
        $this->assertContains(
                '.jpeg', 
                $result
        );
    }*/
    
    public static function usersProvider()
    {
        return [
            [
                'http://www.autowp.ru/users/user1/', 
                '<span class="user"><i class="fa fa-user"></i>&#xa0;<a href="/users/user1">tester</a></span>'
            ],
            [
                'http://www.autowp.ru/users/user9999999999/',
                '<a href="http://www.autowp.ru/users/user9999999999/">http://www.autowp.ru/users/user9999999999/</a>'
            ],
            [
                'http://www.autowp.ru/users/identity/',
                '<span class="user"><i class="fa fa-user"></i>&#xa0;<a href="/users/identity">tester2</a></span>'
            ],
        ];
    }
 
    public static function hyperlinksProvider()
    {
        return [
            ['just.test', 'just.test'],
            ["Multiline\ntest", "Multiline<br />test"],
            ["Test with &ampersand", "Test with &amp;ampersand"],
            ["Test with \"quote", "Test with &quot;quote"],
            [
                "Test with http://example.com/", 
                'Test with <a href="http://example.com/">http://example.com/</a>'
            ],
            [
                "Test with www.example.com/path link",
                'Test with <a href="http://www.example.com/path">http://www.example.com/path</a> link'
            ],
            [
                "Test with https://example.com/",
                'Test with <a href="https://example.com/">https://example.com/</a>'
            ],
            [
                "https://example.com/#hash",
                '<a href="https://example.com/#hash">https://example.com/#hash</a>'
            ],
            [
                "https://example.com/?param=test#hash",
                '<a href="https://example.com/?param=test#hash">https://example.com/?param=test#hash</a>'
            ],
            [
                "1. https://example.com/ 2. www.google.com",
                '1. <a href="https://example.com/">https://example.com/</a> 2. <a href="http://www.google.com">http://www.google.com</a>'
            ],
            [
                '<a href="https://example.com/">https://example.com/</a>',
                '&lt;a href=&quot;<a href="https://example.com/">https://example.com/</a>&quot;&gt;<a href="https://example.com/">https://example.com/</a>&lt;/a&gt;'
            ],
        ];
    }
}