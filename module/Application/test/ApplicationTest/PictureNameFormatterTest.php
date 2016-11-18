<?php

namespace ApplicationTest\Controller;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

use Application\PictureNameFormatter;

class PictureNameFormatterTest extends AbstractHttpControllerTestCase
{
    public function setUp()
    {
        $this->setApplicationConfig(include __DIR__ . '/_files/application.config.php');

        parent::setUp();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFormat($data, $textExpected, $htmlExpected)
    {
        $services = $this->getApplicationServiceLocator();

        $formatter = $services->get(PictureNameFormatter::class);

        $this->assertEquals($textExpected, $formatter->format($data, 'en'));

        $this->assertEquals($htmlExpected, $formatter->formatHtml($data, 'en'));
    }

    /**
     * @dataProvider escapeDataProvider
     */
    public function testEscape($data, $expected)
    {
        $services = $this->getApplicationServiceLocator();

        $formatter = $services->get(PictureNameFormatter::class);

        $html = $formatter->formatHtml($data, 'en');

        $this->assertEquals($expected, $html);
    }

    public function dataProvider()
    {
        return [
            [
                [
                    'type'  => -1
                ],
                'Picture',
                'Picture'
            ],
            [
                [
                    'type'  => 0,
                    'brand' => null
                ],
                'Unsorted',
                'Unsorted'
            ],
            [
                [
                    'type'  => 0,
                    'brand' => 'Toyota'
                ],
                'Toyota unsorted',
                'Toyota unsorted'
            ],
            [
                [
                    'type'  => 2,
                    'brand' => null
                ],
                'Logotype',
                'Logotype'
            ],
            [
                [
                    'type'  => 2,
                    'brand' => 'Toyota'
                ],
                'Toyota logotype',
                'Toyota logotype'
            ],
            [
                [
                    'type'  => 3,
                    'brand' => null
                ],
                'Miscellaneous',
                'Miscellaneous'
            ],
            [
                [
                    'type'  => 3,
                    'brand' => 'Toyota'
                ],
                'Toyota miscellaneous',
                'Toyota miscellaneous'
            ],
            [
                [
                    'type'   => 4,
                    'engine' => null
                ],
                'Engine',
                'Engine'
            ],
            [
                [
                    'type'   => 4,
                    'engine' => 'Mercedes-Benz M112'
                ],
                'Mercedes-Benz M112 engine',
                'Mercedes-Benz M112 engine'
            ],
            [
                [
                    'type'    => 7,
                    'factory' => null
                ],
                'Factory',
                'Factory'
            ],
            [
                [
                    'type'    => 7,
                    'factory' => 'АЗЛК'
                ],
                'АЗЛК',
                'АЗЛК'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => null
                ],
                'Unsorted car',
                'Unsorted car'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name' => 'BMW 3 Series'
                    ]
                ],
                'BMW 3 Series',
                'BMW 3 Series'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => 'Under the hood',
                    'car'         => [
                        'name' => 'BMW 3 Series'
                    ]
                ],
                'Under the hood BMW 3 Series',
                'Under the hood BMW 3 Series'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name' => 'BMW 3 Series',
                        'body' => 'E46'
                    ]
                ],
                'BMW 3 Series (E46)',
                'BMW 3 Series (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name' => 'BMW 3 Series',
                        'body' => 'E46',
                        'spec' => 'UK-spec'
                    ]
                ],
                'BMW 3 Series UK-spec (E46)',
                'BMW 3 Series <span class="label label-primary">UK-spec</span> (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'body'             => 'E46',
                        'spec'             => 'UK-spec',
                        'begin_model_year' => '1999'
                    ]
                ],
                '1999–?? BMW 3 Series UK-spec (E46)',
                '<span title="model&#x20;years">1999–??</span> BMW 3 Series <span class="label label-primary">UK-spec</span> (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'body'             => 'E46',
                        'spec'             => 'UK-spec',
                        'end_model_year'   => '1999'
                    ]
                ],
                '????–1999 BMW 3 Series UK-spec (E46)',
                '<span title="model&#x20;years">????–1999</span> BMW 3 Series <span class="label label-primary">UK-spec</span> (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'body'             => 'E46',
                        'spec'             => 'UK-spec',
                        'begin_model_year' => '1999',
                        'today'            => true
                    ]
                ],
                '1999–pr. BMW 3 Series UK-spec (E46)',
                '<span title="model&#x20;years">1999–pr.</span> BMW 3 Series <span class="label label-primary">UK-spec</span> (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'body'             => 'E46',
                        'spec'             => 'UK-spec',
                        'end_model_year'   => '1999',
                        'today'            => true
                    ]
                ],
                '????–1999 BMW 3 Series UK-spec (E46)',
                '<span title="model&#x20;years">????–1999</span> BMW 3 Series <span class="label label-primary">UK-spec</span> (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'body'             => 'E46',
                        'spec'             => 'UK-spec',
                        'begin_model_year' => date('Y'),
                        'today'            => true
                    ]
                ],
                date('Y') . ' BMW 3 Series UK-spec (E46)',
                '<span title="model&#x20;years">' . date('Y') . '</span> BMW 3 Series <span class="label label-primary">UK-spec</span> (E46)'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'       => 'BMW 3 Series',
                        'body'       => 'E46',
                        'spec'       => 'UK-spec',
                        'begin_year' => 1999
                    ]
                ],
                "BMW 3 Series UK-spec (E46) '1999–????",
                "BMW 3 Series <span class=\"label label-primary\">UK-spec</span> (E46) '1999–????"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'       => 'BMW 3 Series',
                        'body'       => 'E46',
                        'spec'       => 'UK-spec',
                        'begin_year' => 1998,
                        'end_year'   => 1999
                    ]
                ],
                "BMW 3 Series UK-spec (E46) '1998–99",
                "BMW 3 Series <span class=\"label label-primary\">UK-spec</span> (E46) '1998–99"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'       => 'BMW 3 Series',
                        'body'       => 'E46',
                        'spec'       => 'UK-spec',
                        'begin_year' => 1998,
                        'today'      => true
                    ]
                ],
                "BMW 3 Series UK-spec (E46) '1998–pr.",
                "BMW 3 Series <span class=\"label label-primary\">UK-spec</span> (E46) '1998–pr."
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'       => 'BMW 3 Series',
                        'body'       => 'E46',
                        'spec'       => 'UK-spec',
                        'begin_year' => 1998,
                        'end_year'   => 2001
                    ]
                ],
                "BMW 3 Series UK-spec (E46) '1998–2001",
                "BMW 3 Series <span class=\"label label-primary\">UK-spec</span> (E46) '1998–2001"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'       => 'BMW 3 Series',
                        'body'       => 'E46',
                        'spec'       => 'UK-spec',
                        'end_year'   => 2001
                    ]
                ],
                "BMW 3 Series UK-spec (E46) '????–2001",
                "BMW 3 Series <span class=\"label label-primary\">UK-spec</span> (E46) '????–2001"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'        => 'BMW 3 Series',
                        'body'        => 'E46',
                        'spec'        => 'UK-spec',
                        'begin_year'  => 1998,
                        'begin_month' => 11
                    ]
                ],
                "BMW 3 Series UK-spec (E46) '11.1998–????",
                "BMW 3 Series <span class=\"label label-primary\">UK-spec</span> (E46) '<small class=\"month\">11.</small>1998–????"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'        => 'BMW 3 Series',
                        'begin_year'  => 1998,
                        'begin_month' => 11,
                        'end_year'    => 1999
                    ]
                ],
                "BMW 3 Series '11.1998–99",
                "BMW 3 Series '<small class=\"month\">11.</small>1998–99"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'        => 'BMW 3 Series',
                        'begin_year'  => 1998,
                        'begin_month' => 11,
                        'end_year'    => 1999,
                        'end_month'   => 3
                    ]
                ],
                "BMW 3 Series '11.1998–03.1999",
                "BMW 3 Series '<small class=\"month\">11.</small>1998–<small class=\"month\">03.</small>1999"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'        => 'BMW 3 Series',
                        'end_year'    => 1999,
                        'end_month'   => 3
                    ]
                ],
                "BMW 3 Series '????–03.1999",
                "BMW 3 Series '????–<small class=\"month\">03.</small>1999"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'        => 'BMW 3 Series',
                        'end_year'    => 1999,
                    ]
                ],
                "BMW 3 Series '????–1999",
                "BMW 3 Series '????–1999"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'begin_year'       => 1998,
                        'end_year'         => 1999,
                        'begin_model_year' => 1998,
                        'end_model_year'   => 1999,
                    ]
                ],
                "1998–99 BMW 3 Series '1998–99",
                "<span title=\"model&#x20;years\">1998–99</span> BMW 3 Series<small> '<span class=\"realyears\" title=\"years&#x20;of&#x20;production\">1998–99</span></small>"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'begin_model_year' => 1999,
                        'end_model_year'   => 1999,
                    ]
                ],
                "1999 BMW 3 Series",
                "<span title=\"model&#x20;years\">1999</span> BMW 3 Series"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'begin_model_year' => 1998,
                        'end_model_year'   => 1999,
                    ]
                ],
                "1998–99 BMW 3 Series",
                "<span title=\"model&#x20;years\">1998–99</span> BMW 3 Series"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'begin_model_year' => 1998,
                        'end_model_year'   => 2001,
                    ]
                ],
                "1998–2001 BMW 3 Series",
                "<span title=\"model&#x20;years\">1998–2001</span> BMW 3 Series"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'             => 'BMW 3 Series',
                        'begin_year'  => 1998,
                        'end_year'    => 1998,
                        'begin_month' => 10,
                        'end_month'   => 11,
                    ]
                ],
                "BMW 3 Series '10–11.1998",
                "BMW 3 Series '<small class=\"month\">10–11.</small>1998"
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => null,
                    'car'         => [
                        'name'        => 'BMW 3 Series',
                        'begin_year'  => date('Y'),
                    ]
                ],
                "BMW 3 Series '" . date('Y'),
                "BMW 3 Series '" . date('Y')
            ]
        ];
    }

    public function escapeDataProvider()
    {
        return [
            [
                [
                    'type'  => 0,
                    'brand' => 'B&B'
                ],
                'B&amp;B unsorted',
            ],
            [
                [
                    'type'  => 2,
                    'brand' => 'B&B'
                ],
                'B&amp;B logotype',
            ],
            [
                [
                    'type'  => 3,
                    'brand' => 'B&B'
                ],
                'B&amp;B miscellaneous',
            ],
            [
                [
                    'type'   => 4,
                    'engine' => 'B&B M112'
                ],
                'B&amp;B M112 engine',
            ],
            [
                [
                    'type'    => 7,
                    'factory' => 'B&B'
                ],
                'B&amp;B'
            ],
            [
                [
                    'type'        => 1,
                    'perspective' => 'B&B',
                    'car'         => [
                        'name'             => 'B&B',
                        'body'             => 'B&B',
                        'spec'             => 'B&B',
                        'spec_full'        => 'B&B',
                        'begin_year'       => 'B&B',
                        'begin_month'      => 'B&B',
                        'end_year'         => 'B&B',
                        'end_month'        => 'B&B',
                        'begin_model_year' => 'B&B',
                        'end_model_year'   => 'B&B',
                    ]
                ],
                'B&amp;B B&amp;B <span class="label label-primary" title="B&amp;B" data-toggle="tooltip" data-placement="top">B&amp;B</span> (B&amp;B)'
            ],
        ];
    }
}
