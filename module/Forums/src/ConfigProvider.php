<?php

namespace Autowp\Forums;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'translator'   => $this->getTranslatorConfig(),
            'forms'        => $this->getFormsConfig()
        ];
    }

    public function getTranslatorConfig()
    {
        return [
            'translation_file_patterns' => [
                [
                    'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                    'base_dir' => Resources::getBasePath(),
                    'pattern'  => Resources::getPatternForViewHelpers()
                ],
            ]
        ];
    }

    /**
     * Return application-level dependency configuration.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        return [
            'factories' => [
                Forums::class => ForumsFactory::class,
            ]
        ];
    }

    public function getFormsConfig()
    {
        return [
            'ForumsTopicNewForm' => [
                'type'     => \Zend\Form\Form::class,
                'attributes'  => [
                    'method' => 'post',
                ],
                'elements' => [
                    [
                        'spec' => [
                            'type' => 'Text',
                            'name' => 'name',
                            'options' => [
                                'label'     => 'forums/topic/name',
                            ],
                            'attributes' => [
                                'size'      => 80,
                                'maxlength' => 100,
                            ]
                        ],
                    ],
                    [
                        'spec' => [
                            'type' => 'Textarea',
                            'name' => 'text',
                            'options' => [
                                'label'     => 'forums/topic/text',
                            ],
                            'attributes' => [
                                'cols'      => 140,
                                'rows'      => 15,
                                'maxlength' => 1024 * 4
                            ]
                        ],
                    ],
                    [
                        'spec' => [
                            'type' => 'Checkbox',
                            'name' => 'moderator_attention',
                            'options' => [
                                'label' => 'comments/it-requires-attention-of-moderators',
                            ]
                        ],
                    ],
                    [
                        'spec' => [
                            'type' => 'Checkbox',
                            'name' => 'subscribe',
                            'options' => [
                                'label' => 'forums/topic/subscribe-to-new-messages',
                            ]
                        ],
                    ],
                ],
                'input_filter' => [
                    'name' => [
                        'required'   => true,
                        'filters'  => [
                            ['name' => 'StringTrim']
                        ],
                        'validators' => [
                            [
                                'name' => 'StringLength',
                                'options' => [
                                    'min' => 0,
                                    'max' => 100
                                ]
                            ]
                        ]
                    ],
                    'text' => [
                        'required'   => true,
                        'filters'  => [
                            ['name' => 'StringTrim']
                        ],
                        'validators' => [
                            [
                                'name' => 'StringLength',
                                'options' => [
                                    'min' => 0,
                                    'max' => 1024 * 4
                                ]
                            ]
                        ]
                    ],
                ],
            ],
        ];
    }
}
