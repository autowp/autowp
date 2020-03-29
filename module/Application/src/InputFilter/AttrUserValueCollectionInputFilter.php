<?php

namespace Application\InputFilter;

use Application\Service\SpecificationsService;
use Exception;
use InvalidArgumentException;
use Laminas\InputFilter\InputFilter;
use Traversable;

use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function sprintf;

class AttrUserValueCollectionInputFilter extends InputFilter
{
    private array $collectionValues = [];

    private array $collectionRawValues = [];

    private array $collectionMessages = [];

    private SpecificationsService $specService;

    public function __construct(SpecificationsService $specService)
    {
        $this->specService = $specService;
    }

    /**
     * Get the input filter used when looping the data
     */
    public function getInputFilter(int $attributeId): ?parent
    {
        $valueSpec = $this->specService->getFilterSpec($attributeId);
        if (! $valueSpec) {
            return null;
        }

        $spec = [
            'user_id'      => [
                'required'   => true,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'attribute_id' => [
                'required'   => true,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'item_id'      => [
                'required'   => true,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'empty'        => [
                'required'    => false,
                'allow_empty' => true,
                'filters'     => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'value'        => $valueSpec,
        ];

        $inputFilter = new InputFilter();
        foreach ($spec as $name => $input) {
            $inputFilter->add($input, $name);
        }

        return $inputFilter;
    }

    /**
     * @param  null|array|Traversable $data null is cast to an empty array.
     *
     * {@inheritdoc}
     */
    public function setData($data): self
    {
        if (! (is_array($data) || $data instanceof Traversable)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an array or Traversable collection; invalid collection of type %s provided',
                __METHOD__,
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }

        foreach ($data as $item) {
            if (is_array($item) || $item instanceof Traversable) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
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
     *
     * @param null|mixed $context Ignored, but present to retain signature compatibility.
     * @throws Exception
     */
    public function isValid($context = null): bool
    {
        $this->collectionMessages = [];
        $valid                    = true;

        if (! $this->data) {
            $this->clearValues();
            $this->clearRawValues();

            return $valid;
        }

        foreach ($this->data as $key => $data) {
            $inputFilter = $this->getInputFilter($data['attribute_id']);
            if (! $inputFilter) {
                continue;
            }

            $attribute = $this->specService->getAttribute($data['attribute_id']);
            if (! $attribute) {
                throw new Exception("attribute `{$data['attribute_id']}` not found");
            }

            if ($attribute['isMultiple']) {
                $data['value'] = (array) $data['value'];
            }

            $inputFilter->setData($data);

            if ($inputFilter->isValid()) {
                $this->validInputs[$key] = $inputFilter->getValidInput();
            } else {
                $valid                          = false;
                $this->collectionMessages[$key] = $inputFilter->getMessages();
                $this->invalidInputs[$key]      = $inputFilter->getInvalidInput();
            }

            $this->collectionValues[$key]    = $inputFilter->getValues();
            $this->collectionRawValues[$key] = $inputFilter->getRawValues();
        }

        return $valid;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues(): array
    {
        return $this->collectionValues;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawValues(): array
    {
        return $this->collectionRawValues;
    }

    /**
     * Clear collectionValues
     */
    public function clearValues(): array
    {
        return $this->collectionValues = [];
    }

    /**
     * Clear collectionRawValues
     */
    public function clearRawValues(): array
    {
        return $this->collectionRawValues = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getMessages(): array
    {
        return $this->collectionMessages;
    }
}
