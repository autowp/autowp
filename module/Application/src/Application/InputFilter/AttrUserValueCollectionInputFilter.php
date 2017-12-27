<?php

namespace Application\InputFilter;

use Traversable;

use Zend\InputFilter\InputFilter;

use Application\Service\SpecificationsService;

class AttrUserValueCollectionInputFilter extends InputFilter
{
    /**
     * @var array[]
     */
    private $collectionValues = [];

    /**
     * @var array[]
     */
    private $collectionRawValues = [];

    /**
     * @var array
     */
    private $collectionMessages = [];

    /**
     * @var SpecificationsService
     */
    private $specService;

    public function __construct(SpecificationsService $specService)
    {
        $this->specService = $specService;
    }

    /**
     * Get the input filter used when looping the data
     *
     * @return BaseInputFilter
     */
    public function getInputFilter(int $attributeId)
    {
        $spec = [
            'user_id'  => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'attribute_id'  => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'item_id'  => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'value'  => $this->specService->getFilterSpec($attributeId),
        ];

        $inputFilter = new InputFilter();
        foreach ($spec as $name => $input) {
            $inputFilter->add($input, $name);
        }

        return $inputFilter;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        if (! (is_array($data) || $data instanceof Traversable)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable collection; invalid collection of type %s provided',
                __METHOD__,
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }

        foreach ($data as $item) {
            if (is_array($item) || $item instanceof Traversable) {
                continue;
            }

            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects each item in a collection to be an array or Traversable; '
                . 'invalid item in collection of type %s detected',
                __METHOD__,
                is_object($item) ? get_class($item) : gettype($item)
            ));
        }

        $this->data = $data;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @param mixed $context Ignored, but present to retain signature compatibility.
     */
    public function isValid($context = null)
    {
        $this->collectionMessages = [];
        $valid = true;

        if (! $this->data) {
            $this->clearValues();
            $this->clearRawValues();

            return $valid;
        }

        foreach ($this->data as $key => $data) {
            $inputFilter = $this->getInputFilter($data['attribute_id']);
            $inputFilter->setData($data);

            if ($inputFilter->isValid()) {
                $this->validInputs[$key] = $inputFilter->getValidInput();
            } else {
                $valid = false;
                $this->collectionMessages[$key] = $inputFilter->getMessages();
                $this->invalidInputs[$key] = $inputFilter->getInvalidInput();
            }

            $this->collectionValues[$key] = $inputFilter->getValues();
            $this->collectionRawValues[$key] = $inputFilter->getRawValues();
        }

        return $valid;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return $this->collectionValues;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawValues()
    {
        return $this->collectionRawValues;
    }

    /**
     * Clear collectionValues
     *
     * @return array[]
     */
    public function clearValues()
    {
        return $this->collectionValues = [];
    }

    /**
     * Clear collectionRawValues
     *
     * @return array[]
     */
    public function clearRawValues()
    {
        return $this->collectionRawValues = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getMessages()
    {
        return $this->collectionMessages;
    }
}
