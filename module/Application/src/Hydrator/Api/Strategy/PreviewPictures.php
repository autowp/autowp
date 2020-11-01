<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PreviewPictureHydrator as Hydrator;
use ArrayAccess;
use Exception;

use function is_array;

class PreviewPictures extends AbstractHydratorStrategy
{
    protected function getHydrator(): Hydrator
    {
        if (! isset($this->hydrator)) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        /** @var Hydrator $result */
        $result = $this->hydrator;

        return $result;
    }

    /**
     * @param array|ArrayAccess $value
     * @param null|mixed        $context
     * @throws Exception
     */
    public function extract($value, $context = null): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);

        $largeFormat = is_array($context) && isset($context['large_format']) && $context['large_format'];

        $result = [];
        foreach ($value as $row) {
            if ($row) {
                $result[] = $hydrator->extract($row, [
                    'large_format' => $largeFormat,
                ]);
            } else {
                $result[] = null;
            }

            $largeFormat = false;
        }
        return $result;
    }
}
