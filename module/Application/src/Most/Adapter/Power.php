<?php

namespace Application\Most\Adapter;

use ArrayAccess;
use Exception;
use Laminas\Db\Sql;

use function Autowp\Commons\currentFromResultSetInterface;
use function htmlspecialchars;
use function implode;
use function sprintf;
use function strlen;

class Power extends AbstractAdapter
{
    /** @var array|ArrayAccess */
    protected $attribute;

    protected string $order;

    private array $attributes;

    public function setAttributes(array $value): void
    {
        $this->attributes = $value;
    }

    public function setOrder(string $value): void
    {
        $this->order = $value;
    }

    /**
     * @throws Exception
     */
    public function getCars(Sql\Select $select, string $language): array
    {
        $powerAttr = currentFromResultSetInterface(
            $this->attributeTable->select(['id' => (int) $this->attributes['power']])
        );

        $specService = $this->most->getSpecs();

        $valuesTable     = $specService->getValueDataTable($powerAttr['type_id']);
        $valuesTableName = $valuesTable->getTable();

        $select
            ->join($valuesTableName, 'item.id = ' . $valuesTableName . '.item_id', [])
            ->where([
                $valuesTableName . '.attribute_id' => $powerAttr['id'],
                $valuesTableName . '.value > 0',
            ])
            ->group(['item.id', $valuesTableName . '.value'])
            ->order($valuesTableName . '.value ' . $this->order)
            ->limit($this->most->getCarsCount());

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $car) {
            $html  = '';
            $value = $specService->getActualValue($powerAttr['id'], $car['id']);
            $turbo = $specService->getActualValueText($this->attributes['turbo'], $car['id'], $language);
            switch ($turbo) {
                case 'нет':
                    $turbo = null;
                    break;
                case 'есть':
                    $turbo = 'турбонаддув';
                    break;
                default:
                    if ($turbo) {
                        $turbo = 'турбонаддув ' . $turbo;
                    }
                    break;
            }
            $volume           = $specService->getActualValue($this->attributes['volume'], $car['id']);
            $cylindersLayout  = $specService->getActualValueText(
                $this->attributes['cylindersLayout'],
                $car['id'],
                $language
            );
            $cylindersCount   = $specService->getActualValue($this->attributes['cylindersCount'], $car['id']);
            $valvePerCylinder = $specService->getActualValue($this->attributes['valvePerCylinder'], $car['id']);

            $cyl = $this->cylinders($cylindersLayout, $cylindersCount, $valvePerCylinder);

            $html .= $value;
            $html .= ' <span class="unit">л.с.</span>';

            if (strlen($cyl) || $turbo || $volume) {
                $a = [];

                if ($cyl) {
                    $a[] = htmlspecialchars($cyl);
                }

                if ($volume > 0) {
                    $a[] = sprintf('%0.1f <span class="unit">л</span>', $volume / 1000);
                }

                if ($turbo) {
                    $a[] = $turbo;
                }

                $html .= '<p class="note">' . implode(', ', $a) . '</p>';
            }

            $result[] = [
                'car'       => $car,
                'valueHtml' => $html,
            ];
        }

        return [
            'unit' => null, //$attribute->findParentAttrs_Units(),
            'cars' => $result,
        ];
    }

    protected function cylinders(?string $layout, ?int $cylinders, ?int $valvePerCylinder): string
    {
        if ($layout) {
            if ($cylinders) {
                $result = $layout . $cylinders;
            } else {
                $result = $layout . '?';
            }
        } else {
            if ($cylinders) {
                $result = (string) $cylinders;
            } else {
                $result = '';
            }
        }

        if ($valvePerCylinder) {
            $result .= '/' . $valvePerCylinder;
        }

        return $result;
    }
}
