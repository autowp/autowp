<?php

namespace ApplicationTest\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\PictureNameFormatter;

class PictureNameFormatterTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../config/application.config.php';

    /**
     * @dataProvider dataProvider
     * @param $data
     * @param $textExpected
     * @param $htmlExpected
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
     * @param $data
     * @param $expected
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
                    'items' => []
                ],
                'Picture',
                'Picture'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series'
                        ]
                    ]
                ],
                'BMW 3 Series',
                'BMW 3 Series'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => 'Under the hood',
                            'name'        => 'BMW 3 Series'
                        ]
                    ]
                ],
                'Under the hood BMW 3 Series',
                'Under the hood BMW 3 Series'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46'
                        ]
                    ]
                ],
                'BMW 3 Series (E46)',
                'BMW 3 Series (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec'
                        ]
                    ]
                ],
                'BMW 3 Series [UK-spec] (E46)',
                'BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'body'             => 'E46',
                            'spec'             => 'UK-spec',
                            'begin_model_year' => '1999'
                        ]
                    ]
                ],
                '1999–?? BMW 3 Series [UK-spec] (E46)',
                '<span title="model&#x20;years">1999–??</span> BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'    => null,
                            'name'           => 'BMW 3 Series',
                            'body'           => 'E46',
                            'spec'           => 'UK-spec',
                            'end_model_year' => '1999'
                        ]
                    ]
                ],
                '????–1999 BMW 3 Series [UK-spec] (E46)',
                '<span title="model&#x20;years">????–1999</span> BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'body'             => 'E46',
                            'spec'             => 'UK-spec',
                            'begin_model_year' => '1999',
                            'today'            => true
                        ]
                    ]
                ],
                '1999–pr. BMW 3 Series [UK-spec] (E46)',
                '<span title="model&#x20;years">1999–pr.</span> BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'    => null,
                            'name'           => 'BMW 3 Series',
                            'body'           => 'E46',
                            'spec'           => 'UK-spec',
                            'end_model_year' => '1999',
                            'today'          => true
                        ]
                    ]
                ],
                '????–1999 BMW 3 Series [UK-spec] (E46)',
                '<span title="model&#x20;years">????–1999</span> BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'body'             => 'E46',
                            'spec'             => 'UK-spec',
                            'begin_model_year' => date('Y'),
                            'today'            => true
                        ]
                    ]
                ],
                date('Y') . ' BMW 3 Series [UK-spec] (E46)',
                '<span title="model&#x20;years">' . date('Y') . '</span> BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec',
                            'begin_year'  => 1999
                        ]
                    ]
                ],
                "BMW 3 Series [UK-spec] (E46) '1999–????",
                "BMW 3 Series <span class=\"badge badge-info\">UK-spec</span> (E46) '1999–????"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec',
                            'begin_year'  => 1998,
                            'end_year'    => 1999
                        ]
                    ]
                ],
                "BMW 3 Series [UK-spec] (E46) '1998–99",
                "BMW 3 Series <span class=\"badge badge-info\">UK-spec</span> (E46) '1998–99"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec',
                            'begin_year'  => 1998,
                            'today'       => true
                        ]
                    ]
                ],
                "BMW 3 Series [UK-spec] (E46) '1998–pr.",
                "BMW 3 Series <span class=\"badge badge-info\">UK-spec</span> (E46) '1998–pr."
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec',
                            'begin_year'  => 1998,
                            'end_year'    => 2001
                        ]
                    ]
                ],
                "BMW 3 Series [UK-spec] (E46) '1998–2001",
                "BMW 3 Series <span class=\"badge badge-info\">UK-spec</span> (E46) '1998–2001"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec',
                            'end_year'    => 2001
                        ]
                    ]
                ],
                "BMW 3 Series [UK-spec] (E46) '????–2001",
                "BMW 3 Series <span class=\"badge badge-info\">UK-spec</span> (E46) '????–2001"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'body'        => 'E46',
                            'spec'        => 'UK-spec',
                            'begin_year'  => 1998,
                            'begin_month' => 11
                        ]
                    ]
                ],
                "BMW 3 Series [UK-spec] (E46) '11.1998–????",
                "BMW 3 Series <span class=\"badge badge-info\">UK-spec</span> (E46) '<small class=\"month\">11.</small>1998–????"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'begin_year'  => 1998,
                            'begin_month' => 11,
                            'end_year'    => 1999
                        ]
                    ]
                ],
                "BMW 3 Series '11.1998–99",
                "BMW 3 Series '<small class=\"month\">11.</small>1998–99"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'begin_year'  => 1998,
                            'begin_month' => 11,
                            'end_year'    => 1999,
                            'end_month'   => 3
                        ]
                    ]
                ],
                "BMW 3 Series '11.1998–03.1999",
                "BMW 3 Series '<small class=\"month\">11.</small>1998–<small class=\"month\">03.</small>1999"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'end_year'    => 1999,
                            'end_month'   => 3
                        ]
                    ]
                ],
                "BMW 3 Series '????–03.1999",
                "BMW 3 Series '????–<small class=\"month\">03.</small>1999"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'end_year'    => 1999,
                        ]
                    ]
                ],
                "BMW 3 Series '????–1999",
                "BMW 3 Series '????–1999"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'begin_year'       => 1998,
                            'end_year'         => 1999,
                            'begin_model_year' => 1998,
                            'end_model_year'   => 1999,
                        ]
                    ]
                ],
                "1998–99 BMW 3 Series '1998–99",
                "<span title=\"model&#x20;years\">1998–99</span> BMW 3 Series<small> '<span class=\"realyears\" title=\"years&#x20;of&#x20;production\">1998–99</span></small>"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'begin_model_year' => 1999,
                            'end_model_year'   => 1999,
                        ]
                    ]
                ],
                "1999 BMW 3 Series",
                "<span title=\"model&#x20;years\">1999</span> BMW 3 Series"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'begin_model_year' => 1998,
                            'end_model_year'   => 1999,
                        ]
                    ]
                ],
                "1998–99 BMW 3 Series",
                "<span title=\"model&#x20;years\">1998–99</span> BMW 3 Series"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'begin_model_year' => 1998,
                            'end_model_year'   => 2001,
                        ]
                    ]
                ],
                "1998–2001 BMW 3 Series",
                "<span title=\"model&#x20;years\">1998–2001</span> BMW 3 Series"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'begin_year'  => 1998,
                            'end_year'    => 1998,
                            'begin_month' => 10,
                            'end_month'   => 11,
                        ]
                    ]
                ],
                "BMW 3 Series '10–11.1998",
                "BMW 3 Series '<small class=\"month\">10–11.</small>1998"
            ],
            [
                [
                    'items' => [
                        [
                            'perspective' => null,
                            'name'        => 'BMW 3 Series',
                            'begin_year'  => date('Y'),
                        ]
                    ]
                ],
                "BMW 3 Series '" . date('Y'),
                "BMW 3 Series '" . date('Y')
            ],
            [
                [
                    'items' => [
                        [
                            'perspective'      => null,
                            'name'             => 'BMW 3 Series',
                            'body'             => 'E46',
                            'spec'             => 'UK-spec',
                            'begin_model_year' => '1999',
                            'begin_model_year_fraction' => '½',
                            'today'            => true
                        ]
                    ]
                ],
                '1999½–pr. BMW 3 Series [UK-spec] (E46)',
                '<span title="model&#x20;years">1999½–pr.</span> BMW 3 Series <span class="badge badge-info">UK-spec</span> (E46)'
            ],
        ];
    }

    public function escapeDataProvider()
    {
        return [
            [
                [
                    'items' => [
                        [
                            'perspective'      => 'B&B',
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
                    ]
                ],
                'B&amp;B B&amp;B <span class="badge badge-info" title="B&amp;B" data-toggle="tooltip" data-placement="top">B&amp;B</span> (B&amp;B)'
            ],
        ];
    }
}
