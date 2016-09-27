<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Filter\SingleSpaces;

use Attrs_List_Options;

use Exception;

class AttributeListOption extends Form implements InputFilterProviderInterface
{
    /**
     * @var Attrs_List_Options
     */
    private $listOptionsTable;

    private $attribute;

    public function init()
    {
        parent::init();

        $this->addElements([
            ['Select_Db_Table_Tree', 'parent_id', [
                'required'    => false,
                'label'       => 'attrs/list-options/parent',
                'table'       => $listOptions,
                'parentField' => 'parent_id',
                'valueField'  => 'id',
                'viewField'   => 'name',
                'select'      => [
                    'order' => 'position',
                    'where' => [
                        ['attribute_id = ?', $this->_attribute->id]
                    ]
                ],
                'class'       => 'form-control'
            ]],
        ]);
    }

    private function getParents($parentId)
    {
        $db = $this->listOptionsTable->getAdapter();

        $select = $db->select()
            ->from($this->listOptionsTable->info('name'), ['id', 'name'])
            ->where('attribute_id = ?', $this->attribute->id);

        if ($parentId) {
            $select->where('parent_id = ?', $parentId);
        } else {
            $select->where('parent_id is null');
        }

        $result = [];
        foreach ($db->fetchPairs($select) as $id => $name) {
            $result[$id] = $name;
            $result = array_replace($result, $this->getParents($id));
        }

        return $result;
    }

    public function __construct($name = null, $options = [])
    {
        $this->listOptionsTable = new Attrs_List_Options();

        parent::__construct($name, $options);

        if (!$this->attribute) {
            throw new Exception('Attribute not provided');
        }

        $elements = [
            [
                'name'    => 'parent_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'attrs/list-options/parent',
                    'options' => array_replace(['' => '--'], $this->getParents(null))
                ]
            ],
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label' => 'attrs/list-options/name'
                ]
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->setAttribute('method', 'post');
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
        if (isset($options['attribute'])) {
            $this->attribute = $options['attribute'];
            unset($options['attribute']);
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
        return [
            'parent_id' => [
                'required' => false,
            ],
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ]
        ];
    }
}