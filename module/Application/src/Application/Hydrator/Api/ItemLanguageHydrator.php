<?php

declare(strict_types=1);

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\Item as HydratorItemStrategy;

class ItemLanguageHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    private $router;

    /**
     * @var TextStorage
     */
    private $textStorage;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->router = $serviceManager->get('HttpRouter');
        $this->textStorage = $serviceManager->get(\Autowp\TextStorage\Service::class);

        $strategy = new HydratorItemStrategy($serviceManager);
        $this->addStrategy('item', $strategy);
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

    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
