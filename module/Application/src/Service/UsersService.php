<?php

namespace Application\Service;

use Application\Model\Picture;
use ArrayObject;
use Autowp\Comments;
use Autowp\Commons\Db\Table\Row;
use Autowp\Image;
use Autowp\User\Model\User;
use DateTime;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Mail;

use function md5;
use function microtime;
use function round;
use function sprintf;
use function strlen;

use const PHP_EOL;

class UsersService
{
    private string $salt;

    private string $emailSalt;

    private array $hosts;

    private TranslatorInterface $translator;

    private Mail\Transport\TransportInterface $transport;

    private SpecificationsService $specsService;

    private Image\Storage $imageStorage;

    private Comments\CommentsService $comments;

    private Picture $picture;

    private User $userModel;

    private TableGateway $logEventUserTable;

    public function __construct(
        array $options,
        array $hosts,
        TranslatorInterface $translator,
        Mail\Transport\TransportInterface $transport,
        SpecificationsService $specsService,
        Image\Storage $imageStorage,
        Comments\CommentsService $comments,
        Picture $picture,
        User $userModel,
        TableGateway $logEventUserTable
    ) {
        $this->salt      = $options['salt'];
        $this->emailSalt = $options['emailSalt'];

        $this->hosts        = $hosts;
        $this->translator   = $translator;
        $this->transport    = $transport;
        $this->specsService = $specsService;
        $this->imageStorage = $imageStorage;

        $this->comments = $comments;

        $this->picture           = $picture;
        $this->userModel         = $userModel;
        $this->logEventUserTable = $logEventUserTable;
    }

    /**
     * @throws Exception
     */
    public function getPasswordHashExpr(string $password): Sql\Expression
    {
        if (strlen($password) <= 0) {
            throw new Exception("Password cannot be empty");
        }

        return new Sql\Expression('MD5(CONCAT(?, ?))', [$this->salt, $password]);
    }

    private function emailCheckCode(string $email): string
    {
        return md5($this->emailSalt . $email . microtime());
    }

    /**
     * @throws Exception
     */
    private function getHostOptions(string $language): array
    {
        if (! isset($this->hosts[$language])) {
            throw new Exception("Host with language `$language` is not supported");
        }

        return $this->hosts[$language];
    }

    /**
     * @return ArrayObject|array|null
     * @throws Exception
     */
    public function addUser(array $values, string $language)
    {
        $host = $this->getHostOptions($language);

        $emailCheckCode = null;
        if ($values['email']) {
            $emailCheckCode = $this->emailCheckCode($values['email']);
        }

        $this->userModel->getTable()->insert([
            'login'            => null,
            'e_mail'           => null,
            'password'         => $this->getPasswordHashExpr($values['password']),
            'email_to_check'   => $values['email'],
            'hide_e_mail'      => 1,
            'email_check_code' => $emailCheckCode,
            'name'             => $values['name'],
            'reg_date'         => new Sql\Expression('NOW()'),
            'last_online'      => new Sql\Expression('NOW()'),
            'timezone'         => $host['timezone'],
            'last_ip'          => new Sql\Expression('INET6_ATON(?)', [$values['ip']]),
            'language'         => $language,
        ]);
        $userId = $this->userModel->getTable()->getLastInsertValue();
        $user   = $this->userModel->getRow($userId);

        $this->specsService->refreshUserConflicts($userId);

        $this->sendRegistrationConfirmEmail($user, $host['hostname']);

        $this->updateUserVoteLimit($userId);

        $this->userModel->getTable()->update([
            'votes_left' => new Sql\Expression('votes_per_day'),
        ], [
            'id' => $userId,
        ]);

        return $user;
    }

    /**
     * @param array|ArrayObject $user
     */
    public function sendRegistrationConfirmEmail($user, string $hostname): void
    {
        if ($user['email_to_check'] && $user['email_check_code']) {
            $values = [
                'email' => $user['email_to_check'],
                'name'  => $user['name'],
                'url'   => 'https://' . $hostname . '/account/emailcheck/' . $user['email_check_code'],
            ];

            $subject = $this->translator->translate('users/registration/email-confirm-subject');
            $message = $this->translator->translate('users/registration/email-confirm-message');

            $subject = sprintf($subject, $hostname);
            $message = sprintf(
                $message,
                'https://' . $hostname . '/',
                $values['email'],
                $values['url'],
                $hostname
            );

            $mail = new Mail\Message();
            $mail
                ->setEncoding('utf-8')
                ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                ->setBody($message)
                ->addTo($values['email'], $values['name'])
                ->setSubject($subject);

            $this->transport->send($mail);
        }
    }

    /**
     * @throws Exception
     */
    public function updateUsersVoteLimits(): int
    {
        $select = $this->userModel->getTable()->getSql()->select()
            ->columns(['id'])
            ->where([
                'not deleted',
                new Sql\Predicate\Expression('last_online > DATE_SUB(NOW(), INTERVAL 3 MONTH)'),
            ]);

        $affected = 0;
        foreach ($this->userModel->getTable()->selectWith($select) as $row) {
            $this->updateUserVoteLimit($row['id']);
            $affected++;
        }

        return $affected;
    }

    /**
     * @throws Exception
     */
    public function updateUserVoteLimit(int $userId): bool
    {
        $userRow = $this->userModel->getRow($userId);
        if (! $userRow) {
            return false;
        }

        $default = 10;

        $avgVote = $this->comments->getUserAvgVote($userId);

        $age     = 0;
        $regDate = Row::getDateTimeByColumnType('timestamp', $userRow['reg_date']);
        if ($regDate) {
            $now  = new DateTime();
            $diff = $now->getTimestamp() - $regDate->getTimestamp();
            $age  = ((($diff / 60) / 60) / 24) / 365;
        }

        $picturesExists = $this->picture->isExists([
            'user'   => $userId,
            'status' => Picture::STATUS_ACCEPTED,
        ]);

        $value = round($default + $avgVote + $age + $picturesExists / 100);
        if ($value < 0) {
            $value = 0;
        }

        $this->userModel->getTable()->update([
            'votes_per_day' => $value,
        ], [
            'id' => $userId,
        ]);

        return true;
    }

    public function restoreVotes(): void
    {
        $this->userModel->getTable()->update([
            'votes_left' => new Sql\Expression('votes_per_day'),
        ], [
            'votes_left < votes_per_day',
            'not deleted',
        ]);
    }

    /**
     * @throws Exception
     */
    public function deleteUnused(): void
    {
        $table = $this->userModel->getTable();

        $rows = $table->selectWith(
            $table->getSql()->select()
                ->join('attrs_user_values', 'users.id = attrs_user_values.user_id', [], Sql\Select::JOIN_LEFT)
                ->join('comment_message', 'users.id = comment_message.author_id', [], Sql\Select::JOIN_LEFT)
                ->join('forums_topics', 'users.id = forums_topics.author_id', [], Sql\Select::JOIN_LEFT)
                ->join('pictures', 'users.id = pictures.owner_id', [], Sql\Select::JOIN_LEFT)
                ->join('voting_variant_vote', 'users.id = voting_variant_vote.user_id', [], Sql\Select::JOIN_LEFT)
                ->join(['pmf' => 'personal_messages'], 'users.id = pmf.from_user_id', [], Sql\Select::JOIN_LEFT)
                ->join(['pmt' => 'personal_messages'], 'users.id = pmt.to_user_id', [], Sql\Select::JOIN_LEFT)
                ->join('log_events', 'users.id = log_events.user_id', [], Sql\Select::JOIN_LEFT)
                ->where([
                    'users.last_online < DATE_SUB(NOW(), INTERVAL 2 YEAR)',
                    'users.role' => 'user',
                    'attrs_user_values.user_id is null',
                    'comment_message.author_id is null',
                    'forums_topics.author_id is null',
                    'pictures.owner_id is null',
                    'voting_variant_vote.user_id is null',
                    'pmf.from_user_id is null',
                    'pmt.to_user_id is null',
                    'log_events.user_id is null',
                ])
                ->order('users.id')
                ->limit(1000)
        );

        foreach ($rows as $row) {
            print 'Delete ' . $row['id'] . ' ' . $row['name'] . ' ' . PHP_EOL;

            $this->delete($row['id']);
        }
    }

    /**
     * @throws Exception
     */
    private function delete(int $userId): void
    {
        $row = $this->userModel->getRow($userId);
        if (! $row) {
            return;
        }

        $imageId = null;
        if ($row['img']) {
            $imageId = $row['img'];
        }

        $this->logEventUserTable->delete([
            'user_id = ?' => $userId,
        ]);

        $this->userModel->getTable()->delete([
            'id' => $userId,
        ]);

        if ($imageId) {
            $this->imageStorage->removeImage($imageId);
        }
    }
}
