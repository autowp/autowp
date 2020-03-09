<?php

namespace Application;

use Laminas\Form\ElementFactory;

return [
    'form_elements' => [
        'aliases' => [
            'year' => Form\Element\Year::class,
            'Year' => Form\Element\Year::class,
        ],
        'factories' => [
            Form\Element\Year::class => ElementFactory::class,
        ]
    ]
];
