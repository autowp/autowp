<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PreviewPictureHydrator as Hydrator;

class PreviewPictures extends HydratorStrategy
{
    /**
     * @return Hydrator
     */
    protected function getHydrator()
    {
        if (! $this->hydrator) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        return $this->hydrator;
    }

    public function extract($value, $context = null)
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);

        $largeFormat = is_array($context) && isset($context['large_format']) && $context['large_format'];

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row, [
                'large_format' => $largeFormat
            ]);

            $largeFormat = false;
        }
        return $result;
    }
}
