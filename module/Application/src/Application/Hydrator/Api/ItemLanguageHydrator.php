<?php

declare(strict_types=1);

namespace Application\Hydrator\Api;

class ItemLanguageHydrator extends RestHydrator
{
    /**
     * @var TextStorage
     */
    private $textStorage;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->textStorage = $serviceManager->get(\Autowp\TextStorage\Service::class);
    }

    public function extract($object)
    {
        $text = null;
        if ($object['text_id']) {
            $text = $this->textStorage->getText($object['text_id']);
        }

        $fullText = null;
        if ($object['full_text_id']) {
            $fullText = $this->textStorage->getText($object['full_text_id']);
        }

        $result = [
            'language'     => $object['language'],
            'name'         => $object['name'],
            'text_id'      => $object['text_id'],
            'text'         => $text,
            'full_text_id' => $object['full_text_id'],
            'full_text'    => $fullText,
        ];

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
