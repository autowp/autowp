<?php

namespace Application;

use Application\Model\Item;
use Application\Model\Picture;
use ArrayAccess;
use Autowp\Comments\CommentsService;
use Exception;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;
use function sprintf;

class Comments
{
    private const PREVIEW_LENGTH = 60;

    public const PICTURES_TYPE_ID = 1;
    public const ITEM_TYPE_ID     = 2;
    public const VOTINGS_TYPE_ID  = 3;
    public const ARTICLES_TYPE_ID = 4;
    public const FORUMS_TYPE_ID   = 5;

    private CommentsService $service;

    private Picture $picture;

    private TableGateway $articleTable;

    private TableGateway $itemTable;

    public function __construct(
        CommentsService $service,
        Picture $picture,
        TableGateway $articleTable,
        TableGateway $itemTable
    ) {
        $this->service      = $service;
        $this->picture      = $picture;
        $this->articleTable = $articleTable;
        $this->itemTable    = $itemTable;
    }

    /**
     * @param array|ArrayAccess $message
     * @throws Exception
     */
    public function getMessageRowRoute($message): array
    {
        switch ($message['type_id']) {
            case self::PICTURES_TYPE_ID:
                $picture = $this->picture->getRow(['id' => (int) $message['item_id']]);
                if (! $picture) {
                    throw new Exception("Picture `{$message['item_id']}` not found");
                }

                $url = ['/picture', $picture['identity']];
                break;

            case self::ITEM_TYPE_ID:
                $item = currentFromResultSetInterface($this->itemTable->select(['id' => (int) $message['item_id']]));
                if (! $item) {
                    throw new Exception("Item `{$message['item_id']}` not found");
                }
                switch ($item['item_type_id']) {
                    case Item::TWINS:
                        $url = ['/twins', 'group', $item['id']];
                        break;
                    case Item::MUSEUM:
                        $url = ['/museums', $item['id']];
                        break;
                    default:
                        throw new Exception(sprintf(
                            "Failed to build url form message `%s` item_type `%s`",
                            $message['item_id'],
                            $item['item_type_id']
                        ));
                }
                break;

            case self::VOTINGS_TYPE_ID:
                $url = ['/voting', $message['item_id']];
                break;

            case self::ARTICLES_TYPE_ID:
                $article = currentFromResultSetInterface($this->articleTable->select(['id' => $message['item_id']]));
                if (! $article) {
                    throw new Exception("Article `{$message['item_id']}` not found");
                }
                $url = ['/articles', $article['catname']];
                break;

            case self::FORUMS_TYPE_ID:
                $url = ['/forums', 'message', $message['id']];
                break;

            default:
                throw new Exception("Unknown type_id `{$message['type_id']}`");
        }

        return $url;
    }

    public function getMessagePreview(string $message): string
    {
        return StringUtils::getTextPreview($message, [
            'maxlines'  => 1,
            'maxlength' => self::PREVIEW_LENGTH,
        ]);
    }

    public function service(): CommentsService
    {
        return $this->service;
    }
}
