<?php

declare(strict_types=1);

namespace Application;

use Laminas\Form\Form;

return [
    'forms' => [
        'ModerPictureVoteForm2' => [
            'type'         => Form::class,
            'attributes'   => [
                'method' => 'post',
            ],
            'elements'     => [
                [
                    'spec' => [
                        'type'       => 'Text',
                        'name'       => 'reason',
                        'options'    => [
                            'label' => 'moder/picture/acceptance/reason',
                        ],
                        'attributes' => [
                            'size'      => Model\PictureModerVote::MAX_LENGTH,
                            'maxlength' => Model\PictureModerVote::MAX_LENGTH,
                            'class'     => 'form-control',
                        ],
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vote',
                        'options' => [
                            'options' => [
                                '1'  => 'moder/picture/acceptance/want-accept',
                                '-1' => 'moder/picture/acceptance/want-delete',
                            ],
                        ],
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Checkbox',
                        'name'    => 'save',
                        'options' => [
                            'label' => 'Save as template?',
                        ],
                    ],
                ],
            ],
            'input_filter' => [
                'reason' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                    ],
                ],
                'vote'   => [
                    'required' => true,
                ],
                'save'   => [
                    'required' => false,
                ],
            ],
        ],
    ],
];
