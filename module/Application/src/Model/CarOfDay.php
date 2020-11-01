<?php

namespace Application\Model;

use Application\ItemNameFormatter;
use Application\PictureNameFormatter;
use ArrayObject;
use Autowp\Image;
use DateInterval;
use DateTime;
use Exception;
use Facebook;
use GuzzleHttp\Exception\BadResponseException;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\Client;
use Laminas\Http\Request;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Json\Json;
use League\OAuth1\Client\Credentials\TokenCredentials;
use League\OAuth1\Client\Server\Twitter;

use function array_merge;
use function array_replace;
use function array_shift;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function mb_strtoupper;
use function mb_substr;
use function sprintf;

use const PHP_EOL;

class CarOfDay
{
    private TableGateway $table;

    private ItemNameFormatter $itemNameFormatter;

    private Image\Storage $imageStorage;

    private Catalogue $catalogue;

    private TranslatorInterface $translator;

    private Item $itemModel;

    private Perspective $perspective;

    private Picture $picture;

    private PictureNameFormatter $pictureNameFormatter;

    public function __construct(
        TableGateway $table,
        ItemNameFormatter $itemNameFormatter,
        Image\Storage $imageStorage,
        Catalogue $catalogue,
        TranslatorInterface $translator,
        Item $itemModel,
        Perspective $perspective,
        Picture $picture,
        PictureNameFormatter $pictureNameFormatter
    ) {
        $this->itemNameFormatter    = $itemNameFormatter;
        $this->imageStorage         = $imageStorage;
        $this->catalogue            = $catalogue;
        $this->translator           = $translator;
        $this->itemModel            = $itemModel;
        $this->perspective          = $perspective;
        $this->picture              = $picture;
        $this->pictureNameFormatter = $pictureNameFormatter;

        $this->table = $table;
    }

    public function getCarOfDayCandidate(): int
    {
        $sql = '
            SELECT c.id, count(p.id) AS p_count
            FROM item AS c
                INNER JOIN item_parent_cache AS cpc ON c.id=cpc.parent_id
                INNER JOIN picture_item ON cpc.item_id = picture_item.item_id
                INNER JOIN pictures AS p ON picture_item.picture_id=p.id
            WHERE p.status=?
                AND (c.begin_year AND c.end_year OR c.begin_model_year AND c.end_model_year)
                AND c.id NOT IN (SELECT item_id FROM of_day WHERE item_id)
            GROUP BY c.id
            HAVING p_count >= ?
            ORDER BY RAND()
            LIMIT 1
        ';

        /** @var Adapter $adapter */
        $adapter   = $this->table->getAdapter();
        $resultSet = $adapter->query($sql, [Picture::STATUS_ACCEPTED, 5]);
        $row       = $resultSet->current();

        return $row ? (int) $row['id'] : 0;
    }

    public function pick(): bool
    {
        $itemId = $this->getCarOfDayCandidate();
        if ($itemId) {
            print $itemId . "\n";

            $now = new DateTime();
            $this->setItemOfDay($now, $itemId, null);

            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function getCurrent(): ?array
    {
        $select = $this->table->getSql()->select();
        $select->where(['day_date <= CURDATE()'])
            ->order('day_date DESC')
            ->limit(1);

        $row = currentFromResultSetInterface($this->table->selectWith($select));

        return $row ? [
            'item_id' => $row['item_id'],
            'user_id' => $row['user_id'],
        ] : null;
    }

    /**
     * @return array|ArrayObject|null
     * @throws Exception
     */
    private function pictureByPerspective(int $itemId, ?int $perspective)
    {
        return $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => $itemId,
                'perspective'      => $perspective ? $perspective : null,
            ],
            'order'  => 'resolution_desc',
        ]);
    }

    private static function ucfirst(string $str): string
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1));
        return $fc . mb_substr($str, 1);
    }

    /**
     * @throws Exception
     */
    public function putCurrentToTwitter(array $twOptions): void
    {
        $dayRow = currentFromResultSetInterface($this->table->select([
            'day_date = CURDATE()',
            'not twitter_sent',
        ]));

        if (! $dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $car = $this->itemModel->getRow([
            'id' => (int) $dayRow['item_id'],
        ]);

        if (! $car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        /* Hardcoded perspective priority list */
        $perspectives = [10, 1, 7, 8, 11, 3, 7, 12, 4, 8];

        $picture = null;
        foreach ($perspectives as $perspective) {
            $picture = $this->pictureByPerspective($car['id'], $perspective);
            if ($picture) {
                break;
            }
        }

        if (! $picture) {
            $picture = $this->pictureByPerspective($car['id'], null);
        }

        if (! $picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }

        $url = 'https://wheelsage.org/picture/' . $picture['identity'];

        if ((int) $car['item_type_id'] === Item::VEHICLE) {
            $title = $this->translator->translate('car-of-day', 'default', 'en');
        } else {
            $title = $this->translator->translate('theme-of-day', 'default', 'en');
        }

        $text = sprintf(
            self::ucfirst($title) . ': %s %s',
            $this->itemNameFormatter->format($this->itemModel->getNameData($car, 'en'), 'en'),
            $url
        );

        $server = new Twitter([
            'identifier'   => $twOptions['oauthOptions']['consumerKey'],
            'secret'       => $twOptions['oauthOptions']['consumerSecret'],
            'callback_uri' => "http://example.com/",
        ]);

        $tokenCredentials = new TokenCredentials();
        $tokenCredentials->setIdentifier($twOptions['token']['oauth_token']);
        $tokenCredentials->setSecret($twOptions['token']['oauth_token_secret']);

        $url     = 'https://api.twitter.com/1.1/statuses/update.json';
        $params  = [
            'status' => $text,
        ];
        $headers = $server->getHeaders($tokenCredentials, 'POST', $url, $params);

        try {
            $server->createHttpClient()->post($url, [
                'headers'     => $headers,
                'form_params' => $params,
            ]);
        } catch (BadResponseException $e) {
            $response   = $e->getResponse();
            $body       = $response->getBody();
            $statusCode = $response->getStatusCode();

            throw new Exception(
                "Received error [$body] with status code [$statusCode] when retrieving token credentials."
            );
        }

        $this->table->update([
            'twitter_sent' => 1,
        ], [
            'day_date' => $dayRow['day_date'],
        ]);

        print 'ok' . PHP_EOL;
    }

    public function putCurrentToFacebook(array $fbOptions): void
    {
        $dayRow = currentFromResultSetInterface($this->table->select([
            'day_date = CURDATE()',
            'not facebook_sent',
        ]));

        if (! $dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $car = $this->itemModel->getRow([
            'id' => (int) $dayRow['item_id'],
        ]);

        if (! $car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        /* Hardcoded perspective priority list */
        $perspectives = [10, 1, 7, 8, 11, 3, 7, 12, 4, 8];

        $picture = null;
        foreach ($perspectives as $perspective) {
            $picture = $this->pictureByPerspective($car['id'], $perspective);
            if ($picture) {
                break;
            }
        }

        if (! $picture) {
            $picture = $this->pictureByPerspective($car['id'], null);
        }

        if (! $picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }

        $url = 'https://wheelsage.org/picture/' . $picture['identity'];

        if ((int) $car['item_type_id'] === Item::VEHICLE) {
            $title = $this->translator->translate('car-of-day', 'default', 'en');
        } else {
            $title = $this->translator->translate('theme-of-day', 'default', 'en');
        }

        $fb = new Facebook\Facebook([
            'app_id'                => $fbOptions['app_id'],
            'app_secret'            => $fbOptions['app_secret'],
            'default_graph_version' => 'v2.8',
        ]);

        $linkData = [
            'link'    => $url,
            'message' => self::ucfirst($title) . ': '
                . $this->itemNameFormatter->format($this->itemModel->getNameData($car, 'en'), 'en'),
        ];

        try {
            // Returns a `Facebook\FacebookResponse` object
            $fb->post('/296027603807350/feed', $linkData, $fbOptions['page_access_token']);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            return;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            return;
        }

        $this->table->update([
            'facebook_sent' => 1,
        ], [
            'day_date' => $dayRow['day_date'],
        ]);

        print 'ok' . PHP_EOL;
    }

    public function putCurrentToVk(array $vkOptions): void
    {
        $language = 'ru';

        $dayRow = currentFromResultSetInterface($this->table->select([
            'day_date = CURDATE()',
            'not vk_sent',
        ]));

        if (! $dayRow) {
            print 'Day row not found or already sent' . PHP_EOL;
            return;
        }

        $car = $this->itemModel->getRow([
            'id' => (int) $dayRow['item_id'],
        ]);

        if (! $car) {
            print 'Car of day not found' . PHP_EOL;
            return;
        }

        /* Hardcoded perspective priority list */
        $perspectives = [10, 1, 7, 8, 11, 3, 7, 12, 4, 8];

        $picture = null;
        foreach ($perspectives as $perspective) {
            $picture = $this->pictureByPerspective($car['id'], $perspective);
            if ($picture) {
                break;
            }
        }

        if (! $picture) {
            $picture = $this->pictureByPerspective($car['id'], null);
        }

        if (! $picture) {
            print 'Picture not found' . PHP_EOL;
            return;
        }

        $url = 'https://autowp.ru/picture/' . $picture['identity'];

        if ((int) $car['item_type_id'] === Item::VEHICLE) {
            $title = $this->translator->translate('car-of-day', 'default', 'ru');
        } else {
            $title = $this->translator->translate('theme-of-day', 'default', 'ru');
        }

        $text = sprintf(
            self::ucfirst($title) . ': %s',
            $this->itemNameFormatter->format($this->itemModel->getNameData($car, $language), $language)
        );

        $client   = new Client('https://api.vk.com/method/wall.post');
        $response = $client
            ->setMethod(Request::METHOD_POST)
            ->setParameterPost([
                'owner_id'     => $vkOptions['owner_id'],
                'from_group'   => 1,
                'message'      => $text,
                'attachments'  => $url,
                'access_token' => $vkOptions['token'],
                'v'            => '5.73',
            ])
            ->send();

        if (! $response->isSuccess()) {
            throw new Exception("Failed to post to vk" . $response->getReasonPhrase());
        }

        $json = Json::decode($response->getBody(), Json::TYPE_ARRAY);
        if (isset($json['error'])) {
            throw new Exception("Failed to post to vk" . $json['error']['error_msg']);
        }

        $this->table->update([
            'vk_sent' => 1,
        ], [
            'day_date' => $dayRow['day_date'],
        ]);

        print 'ok' . PHP_EOL;
    }

    public function getNextDates(): array
    {
        $now      = new DateTime();
        $interval = new DateInterval('P1D');

        $result = [];

        for ($i = 0; $i < 10; $i++) {
            $dayRow = currentFromResultSetInterface($this->table->select([
                'day_date' => $now->format('Y-m-d'),
                'item_id is not null',
            ]));

            $result[] = [
                'date' => clone $now,
                'free' => ! $dayRow,
            ];

            $now->add($interval);
        }

        return $result;
    }

    public function getItemOfDayPictures(int $itemId, string $language): array
    {
        $carOfDay = $this->itemModel->getRow([
            'id' => (int) $itemId,
        ]);

        $carOfDayPictures = $this->getOrientedPictureList($itemId);

        // images
        $formatRequests = [];
        foreach ($carOfDayPictures as $idx => $picture) {
            if ($picture) {
                $format                        = (int) $idx === 0 ? 'picture-thumb-large' : 'picture-thumb-medium';
                $formatRequests[$format][$idx] = $picture['image_id'];
            }
        }

        $imagesInfo = [];
        foreach ($formatRequests as $format => $requests) {
            $imagesInfo[$format] = $this->imageStorage->getFormatedImages($requests, $format);
        }

        // names
        $notEmptyPics = [];
        foreach ($carOfDayPictures as $idx => $picture) {
            if ($picture) {
                $notEmptyPics[] = $picture;
            }
        }
        $names = $this->picture->getNameData($notEmptyPics, [
            'language' => $language,
        ]);

        $paths = $this->catalogue->getCataloguePaths($carOfDay['id'], [
            'breakOnFirst' => true,
            'toBrand'      => false,
        ]);

        $result = [];
        foreach ($carOfDayPictures as $idx => $row) {
            if ($row) {
                $route = null;
                foreach ($paths as $path) {
                    switch ($path['type']) {
                        case 'brand':
                            $route = ['/picture', $row['identity']];
                            break;
                        case 'brand-item':
                            $route = array_merge(
                                ['/', $path['brand_catname'], $path['car_catname']],
                                $path['path'],
                                ['pictures',  $row['identity']]
                            );
                            break;
                        case 'category':
                            $route = ['/category', $path['category_catname'], 'pictures', $row['identity']];
                            break;
                        case 'person':
                            $route = ['/persons', $path['id']];
                            break;
                    }
                }

                $format = (int) $idx === 0 ? 'picture-thumb-large' : 'picture-thumb-medium';
                $thumb  = $imagesInfo[$format][$idx] ?? null;

                $result[] = [
                    'thumb' => $thumb
                        ? [
                            'src'    => $thumb->getSrc(),
                            'width'  => $thumb->getWidth(),
                            'height' => $thumb->getHeight(),
                        ]
                        : null,
                    'name'  => isset($names[$row['id']])
                        ? $this->pictureNameFormatter->format($names[$row['id']], $language)
                        : null,
                    'route' => $route,
                ];
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    private function getOrientedPictureList(int $itemId): array
    {
        $perspectivesGroupIds = $this->perspective->getPageGroupIds(5);

        $pictures = [];

        $usedIds = [];

        foreach ($perspectivesGroupIds as $groupId) {
            $picture = null;

            $select = $this->picture->getSelect([
                'id_exclude' => $usedIds,
                'status'     => Picture::STATUS_ACCEPTED,
                'item'       => [
                    'ancestor_or_self' => $itemId,
                ],
            ]);

            $select
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    [],
                    $select::JOIN_RIGHT
                )
                ->join('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', [], $select::JOIN_LEFT)
                ->where(['mp.group_id' => $groupId])
                ->order([
                    'ipc_ancestor.sport',
                    'ipc_ancestor.tuning',
                    'mp.position',
                    'picture_vote_summary.positive DESC',
                    'pictures.width DESC',
                    'pictures.height DESC',
                ])
                ->group(['ipc_ancestor.sport', 'ipc_ancestor.tuning', 'mp.position'])
                ->limit(1);

            $picture = currentFromResultSetInterface($this->picture->getTable()->selectWith($select));

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[]  = $picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        $resorted = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $resorted[] = $picture;
            }
        }
        foreach ($pictures as $picture) {
            if (! $picture) {
                $resorted[] = null;
            }
        }
        $pictures = $resorted;

        $left = [];
        foreach ($pictures as $key => $picture) {
            if (! $picture) {
                $left[] = $key;
            }
        }

        if (count($left) > 0) {
            $rows = $this->picture->getRows([
                'id_exclude' => $usedIds,
                'status'     => Picture::STATUS_ACCEPTED,
                'item'       => [
                    'ancestor_or_self' => $itemId,
                ],
                'limit'      => count($left),
            ]);

            foreach ($rows as $pic) {
                $key            = array_shift($left);
                $pictures[$key] = $pic;
            }
        }

        return $pictures;
    }

    public function isComplies(int $itemId): bool
    {
        $sql = '
            SELECT item.id, count(distinct pictures.id) AS p_count
            FROM item
                INNER JOIN item_parent_cache AS cpc ON item.id = cpc.parent_id
                INNER JOIN picture_item ON cpc.item_id = picture_item.item_id
                INNER JOIN pictures ON picture_item.picture_id = pictures.id
            WHERE pictures.status = ?
                AND item.id NOT IN (SELECT item_id FROM of_day WHERE item_id)
                AND item.id = ?
            HAVING p_count >= ?
            LIMIT 1
        ';
        /** @var Adapter $adapter */
        $adapter   = $this->table->getAdapter();
        $resultSet = $adapter->query($sql, [Picture::STATUS_ACCEPTED, $itemId, 3]);
        $row       = $resultSet->current();

        return (bool) $row;
    }

    public function setItemOfDay(DateTime $dateTime, int $itemId, ?int $userId): bool
    {
        if (! $this->isComplies($itemId)) {
            return false;
        }

        $dateStr = $dateTime->format('Y-m-d');

        $primaryKey = [
            'day_date' => $dateStr,
        ];

        $dayRow = currentFromResultSetInterface($this->table->select($primaryKey));

        if ($dayRow && $dayRow['item_id']) {
            return false;
        }

        $set = [
            'item_id' => $itemId,
            'user_id' => $userId,
        ];

        if ($dayRow) {
            $this->table->update($set, $primaryKey);
        } else {
            $this->table->insert(array_replace($set, $primaryKey));
        }

        return true;
    }
}
