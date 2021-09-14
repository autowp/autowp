<?php

namespace Application\Command;

use Application\HostManager;
use Application\Model\Log;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\Service\TelegramService;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Exception;
use Laminas\I18n\Translator\TranslatorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function htmlspecialchars;
use function sleep;
use function sprintf;
use function urlencode;

use const PHP_EOL;

class CatalogueAcceptOldUnsortedCommand extends Command
{
    private MessageService $message;

    private Picture $picture;

    private User $userModel;

    private HostManager $hostManager;

    private TelegramService $telegram;

    private TranslatorInterface $translator;

    private Log $log;

    private PictureNameFormatter $pictureNameFormatter;

    /** @var string|null The default command name */
    protected static $defaultName = 'catalogue-accept-old-unsorted';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(
        string $name,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        Picture $picture,
        User $userModel,
        TranslatorInterface $translator,
        Log $log,
        PictureNameFormatter $pictureNameFormatter
    ) {
        parent::__construct($name);

        $this->hostManager          = $hostManager;
        $this->telegram             = $telegram;
        $this->message              = $message;
        $this->picture              = $picture;
        $this->userModel            = $userModel;
        $this->translator           = $translator;
        $this->log                  = $log;
        $this->pictureNameFormatter = $pictureNameFormatter;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $select = $this->picture->getTable()->getSql()->select();
        $select
            ->where([
                // 'type = ?'   => Picture::UNSORTED_TYPE_ID,
                'status = ?' => Picture::STATUS_INBOX,
                'add_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)',
            ])
            ->order('id');

        $rows = $this->picture->getTable()->selectWith($select);

        $userId = 9;

        foreach ($rows as $picture) {
            print $picture['id'] . PHP_EOL;

            $previousStatusUserId = (int) $picture['change_status_user_id'];

            $isFirstTimeAccepted = false;
            $success             = $this->picture->accept($picture['id'], $userId, $isFirstTimeAccepted);
            if ($success && $isFirstTimeAccepted) {
                $owner = $this->userModel->getRow((int) $picture['owner_id']);
                if ($owner && ((int) $owner['id'] !== $userId)) {
                    $uri = $this->hostManager->getUriByLanguage($owner['language']);
                    $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                    $message = sprintf(
                        $this->translator->translate('pm/your-picture-accepted-%s', 'default', $owner['language']),
                        $uri->toString()
                    );

                    $this->message->send(null, $owner['id'], $message);
                }

                $this->telegram->notifyPicture($picture['id']);
            }

            if ($previousStatusUserId !== $userId) {
                $prevUser = $this->userModel->getRow($previousStatusUserId);
                if ($prevUser) {
                    $uri = $this->hostManager->getUriByLanguage($prevUser['language']);

                    $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                    $message = sprintf(
                        'Принята картинка %s',
                        $uri->toString()
                    );
                    $this->message->send(null, $prevUser['id'], $message);
                }
            }

            $language = 'en';

            $names = $this->picture->getNameData([$picture], [
                'language' => $language,
                'large'    => true,
            ]);
            $name  = $names[$picture['id']];

            $this->log->addEvent(9, sprintf(
                'Картинка %s принята',
                htmlspecialchars($this->pictureNameFormatter->format($name, $language))
            ), [
                'pictures' => $picture['id'],
            ]);

            sleep(20);
        }

        return 0;
    }
}
