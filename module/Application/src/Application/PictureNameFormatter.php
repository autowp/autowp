<?php

namespace Application;

use Application\Model\DbTable\Picture\Row as PictureRow;

use Picture;

class PictureNameFormatter
{
    private $translator;

    /**
     * @var VehicleNameFormatter
     */
    private $vehicleNameFormatter;

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function __construct($translator, VehicleNameFormatter $vehicleNameFormatter)
    {
        $this->translator = $translator;
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
                return
                    ($picture['perspective'] ? self::mbUcfirst($this->translate($picture['perspective'], $language)) . ' ' : '') .
                    ($picture['car'] ? $this->vehicleNameFormatter->format($picture['car'], $language) : 'Unsorted car');
                break;

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
}