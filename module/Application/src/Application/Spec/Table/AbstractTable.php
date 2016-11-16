<?php

namespace Application\Spec\Table;

use Zend\View\Renderer\PhpRenderer;

abstract class AbstractTable
{
    protected $attributes = [];

    protected $renderMap = [
        85 => [
            'name' => 'wheel',
            'options'  => [
                'tyrewidth'  => 87,
                'tyreseries' => 90,
                'radius'     => 88,
                'rimwidth'   => 89
            ]
        ],
        86 => [
            'name' => 'wheel',
            'options'  => [
                'tyrewidth'  => 91,
                'tyreseries' => 94,
                'radius'     => 92,
                'rimwidth'   => 93
            ]
        ],
        19 => [
            'name' => 'enginePlacement',
            'options'  => [
                'placement'   => 20,
                'orientation' => 21,
            ]
        ],
        60 => [
            'name' => 'bootVolume',
            'options'  => [
                'min' => 61,
                'max' => 62,
            ]
        ],
        57 => [
            'name' => 'fuelTank',
            'options'  => [
                'primary'   => 58,
                'secondary' => 59,
            ]
        ],
        24 => [
            'name' => 'engineConfiguration',
            'options'  => [
                'cylindersCount'  => 25,
                'cylindersLayout' => 26,
                'valvesCount'     => 27
            ]
        ],
        42 => [
            'name' => 'gearbox',
            'options'  => [
                'type'  => 43,
                'gears' => 44,
                'name'  => 139,
            ]
        ],
    ];

    public function preventedRenderSubAttributes($attrId)
    {
        return isset($this->renderMap[$attrId]);
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    protected function getRenderer($name, $options)
    {
        $className = 'Application\\Spec\\Table\\Value\\' . ucfirst($name);
        return new $className($options);
    }

    public function renderValue(PhpRenderer $view, $attribute, $values, $itemTypeId, $itemId)
    {
        $attrId = $attribute['id'];

        $value = isset($values[$attrId]) ? $values[$attrId] : null;

        if (isset($this->renderMap[$attrId])) {
            $map = $this->renderMap[$attrId];
            $rendererName = $map['name'];
            $rendererOptions = $map['options'];
        } else {
            $rendererName = 'defaultValue';
            $rendererOptions = [];
        }

        $renderer = $this->getRenderer($rendererName, $rendererOptions);
        return $renderer->render($view, $attribute, $value, $values, $itemTypeId, $itemId);
    }
}
