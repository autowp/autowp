<?php

namespace Application\Hydrator\Api;

use ArrayAccess;
use Autowp\TextStorage;
use Exception;
use Laminas\ServiceManager\ServiceLocatorInterface;

class ItemLanguageHydrator extends AbstractRestHydrator
{
    private TextStorage\Service $textStorage;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->textStorage = $serviceManager->get(TextStorage\Service::class);
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $text = null;
        if ($object['text_id']) {
            $text = $this->textStorage->getText($object['text_id']);
        }

        $fullText = null;
        if ($object['full_text_id']) {
            $fullText = $this->textStorage->getText($object['full_text_id']);
        }

        return [
            'language'     => $object['language'],
            'name'         => $object['name'],
            'text_id'      => $object['text_id'],
            'text'         => $text,
            'full_text_id' => $object['full_text_id'],
            'full_text'    => $fullText,
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }
}
