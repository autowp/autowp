<?php

namespace Application;

use Zend\View\Renderer\PhpRenderer;

use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Picture\Row as PictureRow;

class PictureNameFormatter
{
    private $translator;

    /**
     * @var VehicleNameFormatter
     */
    private $vehicleNameFormatter;

    /**
     * @var PhpRenderer
     */
    private $renderer;

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function __construct(
        $translator,
        PhpRenderer $renderer,
        VehicleNameFormatter $vehicleNameFormatter
    ) {

        $this->translator = $translator;
        $this->renderer = $renderer;
        $this->vehicleNameFormatter = $vehicleNameFormatter;
    }

    private function translate($string, $language)
    {
        return $this->translator->translate($string, 'default', $language);
    }

    public function format($picture, $language)
    {
        if ($picture instanceof PictureRow) {
            $pictureTable = new Picture();
            $names = $pictureTable->getNameData([$picture->toArray()], [
                'language' => $language,
                'large'    => true
            ]);
            $picture = $names[$picture->id];
        }

        if (isset($picture['name']) && $picture['name']) {
            return $picture['name'];
        }

        switch ($picture['type']) {
            case Picture::VEHICLE_TYPE_ID:
                $result = [];
                if ($picture['perspective']) {
                    $result[] = self::mbUcfirst($this->translate($picture['perspective'], $language));
                }
                if ($picture['car']) {
                    $result[] = $this->vehicleNameFormatter->format($picture['car'], $language);
                } else {
                    $result[] = 'Unsorted car';
                }
                return implode(' ', $result);

            case Picture::ENGINE_TYPE_ID:
                if ($picture['engine']) {
                    return sprintf($this->translate('picturelist/engine-%s', $language), $picture['engine']);
                } else {
                    return $this->translate('picturelist/engine', $language);
                }
                break;

            case Picture::LOGO_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($this->translate('picturelist/logotype-%s', $language), $picture['brand']);
                } else {
                    return $this->translate('picturelist/logotype', $language);
                }
                break;

            case Picture::MIXED_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($this->translate('picturelist/mixed-%s', $language), $picture['brand']);
                } else {
                    return $this->translate('picturelist/mixed', $language);
                }
                break;

            case Picture::UNSORTED_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($this->translate('picturelist/unsorted-%s', $language), $picture['brand']);
                } else {
                    return $this->translate('picturelist/unsorted', $language);
                }
                break;

            case Picture::FACTORY_TYPE_ID:
                return $picture['factory'];
                break;
        }

        return 'Picture';
    }

    public function formatHtml(array $picture, $language)
    {
        $view = $this->view;

        if (isset($picture['name']) && $picture['name']) {
            return $this->renderer->escapeHtml($picture['name']);
        }

        switch ($picture['type']) {
            case Picture::VEHICLE_TYPE_ID:
                if ($picture['car']) {
                    $result = [];
                    if ($picture['perspective']) {
                        $perspective = $this->translate($picture['perspective'], $language);
                        $result[] = $this->renderer->escapeHtml(self::mbUcfirst($perspective));
                    }
                    if ($picture['car']) {
                        $result[] = $this->vehicleNameFormatter->formatHtml($picture['car'], $language);
                    } else {
                        $result[] = 'Unsorted car';
                    }
                    return implode(' ', $result);
                }
                return 'Unsorted car';

            case Picture::ENGINE_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
            case Picture::FACTORY_TYPE_ID:
                return $view->escapeHtml($this->textTitle($picture));
                break;
        }

        return 'Picture';
    }
}
