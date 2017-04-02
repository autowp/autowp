<?php

namespace Application\Hydrator\Api;

use Zend\Permissions\Acl\Acl;

use Autowp\User\Model\DbTable\User;

use Application\Comments;
use Application\DuplicateFinder;
use Application\Hydrator\Api\Strategy\Image as HydratorImageStrategy;
use Application\Hydrator\Api\Strategy\User as HydratorUserStrategy;
use Application\Model\DbTable;
use Application\Model\PictureItem;
use Application\Model\PictureVote;
use Application\PictureNameFormatter;

use Zend_Db_Expr;

class PictureHydrator extends RestHydrator
{
    /**
     * @var Comments
     */
    private $comments;
    
    private $acl;
    
    /**
     * @var PictureVote
     */
    private $pictureVote;
    
    /**
     * @var int|null
     */
    private $userId = null;
    
    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;
    
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;
    
    /**
     * @var User
     */
    private $userTable;
    
    /**
     * @var DbTable\Picture\ModerVote
     */
    private $moderVoteTable;
    
    /**
     * @var DuplicateFinder
     */
    private $duplicateFinder;
    
    /**
     * @var PictureItem
     */
    private $pictureItem;
    
    public function __construct(
        $serviceManager
    ) {
        parent::__construct();
        
        $this->pictureViewTable = new DbTable\Picture\View();
        $this->pictureTable = new DbTable\Picture();
        $this->userTable = new User();
        $this->moderVoteTable = new DbTable\Picture\ModerVote();
        
        $this->router = $serviceManager->get('HttpRouter');
        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
        $this->pictureVote = $serviceManager->get(\Application\Model\PictureVote::class);
        $this->comments = $serviceManager->get(\Application\Comments::class);
        $this->pictureNameFormatter = $serviceManager->get(PictureNameFormatter::class);
        $this->duplicateFinder = $serviceManager->get(DuplicateFinder::class);
        $this->pictureItem = $serviceManager->get(PictureItem::class);
        
        $strategy = new HydratorImageStrategy($serviceManager);
        $this->addStrategy('picture-thumb', $strategy);
        
        $strategy = new HydratorUserStrategy($serviceManager);
        $this->addStrategy('owner', $strategy);
    }
    
    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);
    
        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }
    
        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }
    
        return $this;
    }
    
    /**
     * @param int|null $userId
     * @return Comment
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;
    
        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);
    
        return $this;
    }
    
    public function extract($object)
    {
        $cropped = DbTable\Picture\Row::checkCropParameters($object);
        
        $votes = $this->pictureVote->getVote($object['id'], null);
        
        $msgCount = $this->comments->service()->getMessagesCount(
            \Application\Comments::PICTURES_TYPE_ID,
            $object['id']
        );
        
        $newMessages = $this->comments->service()->getNewMessages(
            \Application\Comments::PICTURES_TYPE_ID,
            $object['id'],
            $this->userId
        );
        
        $nameDatas = $this->pictureTable->getNameData([$object], [
            'language' => $this->language
        ]);
        $nameData = $nameDatas[$object['id']];
        
        // moder votes
        $moderVotes = null;
        $db = $this->moderVoteTable->getAdapter();
    
        $moderVotes = $db->fetchRow(
            $db->select()
                ->from($this->moderVoteTable->info('name'), [
                    'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                    'count' => 'count(1)'
                ])
                ->where('picture_id = ?', $object['id'])
        );
        
        $picture = [
            'id'             => (int)$object['id'],
            'url'            => $this->router->assemble([
                'picture_id' => $object['identity']
            ], [
                'name' => 'picture/picture'
            ]),
            'name'           => $this->pictureNameFormatter->format($nameData, $this->language),
            'nameHtml'       => $this->pictureNameFormatter->formatHtml($nameData, $this->language),
            'moderVote'      => $moderVotes,
            'views'          => $this->pictureViewTable->get($object),
            'resolution'     => (int)$object['width'] . '×' . (int)$object['height'],
            'cropped'        => $cropped,
            'cropResolution' => $cropped ? $object['crop_width'] . '×' . $object['crop_height'] : null,
            'status'         => $object['status'],
            'msgCount'       => $msgCount,
            'newMsgCount'    => $newMessages,
            //'ownerId'        => $row['owner_id'],
            'votes'          => $votes
        ];
        
        $picture['thumbnail'] = $this->extractValue('picture-thumb', [
            'image'  => DbTable\Picture\Row::buildFormatRequest($object->toArray()),
            'format' => 'picture-thumb'
        ]);
        
        $owner = null;
        if ($object['owner_id']) {
            $owner = $this->userTable->find($object['owner_id'])->current();
        }
        
        if ($owner) {
            $picture['owner'] = $this->extractValue('owner', $owner);
        }
        
        $picture['similar'] = null;
        $similar = $this->duplicateFinder->findSimilar($object['id']);
        if ($similar) {
            $similarRow = $this->pictureTable->find($similar['picture_id'])->current();
            if ($similarRow) {
                $picture['similar'] = [
                    'url'      => $this->router->assemble([
                        'picture_id' => $similarRow['identity']
                    ], [
                        'name' => 'picture/picture'
                    ]),
                    'distance' => $similar['distance']
                ];
            }
        }
        
        $itemIds = $this->pictureItem->getPictureItemsByType($object['id'], [
            DbTable\Item\Type::VEHICLE,
            DbTable\Item\Type::BRAND
        ]);
        
        if (count($itemIds) == 1) {
            $itemId = $itemIds[0];
        
            $perspective = $this->pictureItem->getPerspective($object['id'], $itemId);
        
            $picture['perspective_item'] = [
                'item_id'        => (int)$itemId,
                'perspective_id' => $perspective ? (int)$perspective : null
            ];
        }
        
        return $picture;
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}