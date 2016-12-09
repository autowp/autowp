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

                if (count($picture['items']) > 1) {
                    foreach ($picture['items'] as $item) {
                        $result[] = $item['name'];
                    }

                    return implode(', ', $result);
                } elseif (count($picture['items']) == 1) {
                    $item = $picture['items'][0];

                    $result = [];
                    if ($item['perspective']) {
                        $result[] = self::mbUcfirst($this->translate($item['perspective'], $language));
                    }

                    $result[] = $this->vehicleNameFormatter->format($item, $language);

                    return implode(' ', $result);
                }

                return 'Unsorted vehicle';

            case Picture::LOGO_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($this->translate('picturelist/logotype-%s', $language), $picture['brand']);
                }
                return $this->translate('picturelist/logotype', $language);

            case Picture::MIXED_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($this->translate('picturelist/mixed-%s', $language), $picture['brand']);
                }
                return $this->translate('picturelist/mixed', $language);

            case Picture::UNSORTED_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($this->translate('picturelist/unsorted-%s', $language), $picture['brand']);
                }
                return $this->translate('picturelist/unsorted', $language);

            case Picture::FACTORY_TYPE_ID:
                if ($picture['factory']) {
                    return $picture['factory'];
                }
                return $this->translate('picturelist/factory', $language);
        }

        return 'Picture';
    }

    public function formatHtml(array $picture, $language)
    {
        if (isset($picture['name']) && $picture['name']) {
            return $this->renderer->escapeHtml($picture['name']);
        }

        switch ($picture['type']) {
            case Picture::VEHICLE_TYPE_ID:
                $result = [];
                if (count($picture['items']) > 1) {
                    foreach ($picture['items'] as $item) {
                        $result[] = $this->renderer->escapeHtml($item['name']);
                    }
                    return implode(', ', $result);
                } elseif (count($picture['items']) == 1) {
                    $item = $picture['items'][0];

                    $result = [];
                    if ($item['perspective']) {
                        $perspective = $this->translate($item['perspective'], $language);
                        $result[] = $this->renderer->escapeHtml(self::mbUcfirst($perspective, $language));
                    }

                    $result[] = $this->vehicleNameFormatter->formatHtml($item, $language);
                    return implode(' ', $result);
                }

                return 'Unsorted vehicle';

            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
            case Picture::FACTORY_TYPE_ID:
                return $this->renderer->escapeHtml($this->format($picture, $language));
                break;
        }

        return 'Picture';
    }
}
