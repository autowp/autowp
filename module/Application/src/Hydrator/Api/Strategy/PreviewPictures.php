<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PreviewPictureHydrator as Hydrator;
use ArrayAccess;

use function is_array;

class PreviewPictures extends AbstractHydratorStrategy
{
    protected function getHydrator(): Hydrator
    {
        if (! isset($this->hydrator)) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        return $this->hydrator;
    }

    /**
     * @param array|ArrayAccess $value
     * @param null|mixed        $context
     */
    public function extract($value, $context = null): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);

        $largeFormat = is_array($context) && isset($context['large_format']) && $context['large_format'];

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row, [
                'large_format' => $largeFormat,
            ]);

            $largeFormat = false;
        }
        return $result;
    }
}
