<?php

namespace Application\Service;

use ErrorException;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

use function count;
use function sleep;

class RabbitMQ
{
    private array $config;

    private AMQPStreamConnection $connection;

    /** @var AMQPChannel[] */
    private array $channels = [];

    private string $consumerTag = 'consumer';

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        if (isset($this->connection)) {
            return;
        }

        $connection = null;
        $exception  = null;
        for ($i = 0; $i < 3 && ! $connection; $i++) {
            try {
                $connection = new AMQPStreamConnection(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['user'],
                    $this->config['password'],
                    $this->config['vhost']
                );
            } catch (Exception $e) {
                $exception = $e;
                sleep(1);
            }
        }

        if (! $connection) {
            if ($exception) {
                throw $exception;
            }
            throw new Exception('Failed to connect wihtout exception');
        }

        $this->connection = $connection;
    }

    public function disconnect(): void
    {
        $this->channels = [];

        if (isset($this->connection)) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    private function getChannel(string $queue): AMQPChannel
    {
        if (isset($this->channels[$queue])) {
            return $this->channels[$queue];
        }

        $this->connect();

        $channel = $this->connection->channel();
        $channel->queue_declare($queue, false, false, false, false);
        $channel->exchange_declare($queue, 'direct', false, true, false);
        $channel->queue_bind($queue, $queue);

        $this->channels[$queue] = $channel;

        return $channel;
    }

    public function send(string $queue, string $body): void
    {
        $message = new AMQPMessage($body, [
            'content_type'  => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        $this->getChannel($queue)->basic_publish($message, $queue);
    }

    /**
     * @param callable $callback
     * @throws ErrorException
     */
    public function consume(string $queue, int $timeout, $callback): void
    {
        $channel = $this->getChannel($queue);

        $channel->basic_consume($queue, $this->consumerTag, false, false, false, false, $callback);

        try {
            while (count($channel->callbacks)) {
                $channel->wait(null, false, $timeout);
            }
        } finally {
            $channel->basic_cancel($this->consumerTag);
            $channel->close();
            unset($this->channels[$queue]);
        }
    }
}
