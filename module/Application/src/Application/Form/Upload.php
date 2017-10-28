<?php

namespace Application\Form;

use Traversable;

use Zend\Form\Exception;
use Zend\Form\Element;
use Zend\Form\ElementInterface;
use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator;

class Upload extends Form implements InputFilterProviderInterface
{
    /**
     * @var int
     */
    private $maxFileSize = 20485760; //1024*1024*4;

    /**
     * @var bool
     */
    private $multipleFiles;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $attributes = [];
        if ($this->multipleFiles) {
            $attributes = [
                'multiple' => true
            ];
        }

        $elements = [
            [
                'name'    => 'picture',
                'type'    => 'File',
                'options' => [
                    'label' => 'upload/image-file'
                ],
                'attributes' => $attributes
            ],
            [
                'name'    => 'note',
                'type'    => 'Textarea',
                'options' => [
                    'label' => 'upload/note'
                ],
                'attributes' => [
                    'rows' => 3,
                ]
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->setAttribute('method', 'post');
    }

    public function setInheritedIsConcept($value)
    {
        $this->inheritedIsConcept = $value === null ? null : (bool)$value;

        return $this;
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - use_as_base_fieldset: is this fieldset use as the base fieldset?
     *
     * @param  array|Traversable $options
     * @return Element|ElementInterface
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if (isset($options['maxFileSize'])) {
            $this->maxFileSize = $options['maxFileSize'];
            unset($options['maxFileSize']);
        }

        if (isset($options['multipleFiles'])) {
            $this->multipleFiles = $options['multipleFiles'];
            unset($options['multipleFiles']);
        }

        parent::setOptions($options);

        return $this;
    }

    /**
     * Should return an array specification compatible with
     * {@link Zend\InputFilter\Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification()
    {
        $validators = [
            [
                'name'    => Validator\File\Size::class,
                'options' => [
                    'max'           => $this->maxFileSize,
                    'useByteString' => false
                ]
            ],
            ['name' => Validator\File\IsImage::class],
            [
                'name' => Validator\File\Extension::class,
                'options' => [
                    'extension' => 'jpg,jpeg,jpe,png'
                ]
            ],
            [
                'name' => Validator\File\ImageSize::class,
                'options' => [
                    'minWidth'  => 640,
                    'minHeight' => 360,
                    'maxWidth'  => 4096,
                    'maxHeight' => 4096
                ]
            ]
        ];
        /*if ($this->multipleFiles) {
            array_unshift($validators, [
                'name'    => Validator\File\Count::class,
                'options' => [
                    'min' => 1,
                    'max' => 1
                ]
            ]);
        }*/

        $picture = [
            'required'   => true,
            'validators' => $validators
        ];

        if (defined('IS_PHPUNIT')) {
            $picture['type'] = 'Application\InputFilter\UnitTestFileInput';
        }

        return [
            'picture' => $picture,
            'note' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators'  => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 3,
                            'max' => 4096
                        ]
                    ]
                ]
            ],
        ];
    }
}
