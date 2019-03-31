<?php

namespace Application\Service;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Rollbar\Rollbar;
use Rollbar\Payload\Level;

class RabbitMQ
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var AMQPStreamConnection
     */
    private $connection;

    /**
     * @var AMQPChannel[]
     */
    private $channels = [];

    /**
     * @var string
     */
    private $consumerTag = 'consumer';

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect()
    {
        if ($this->connection) {
            return;
        }

        $connection = null;
        $exception = null;
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
                Rollbar::log(Level::ERROR, $e);
                sleep(1);
            }
        }

        if ($exception && ! $connection) {
            throw $exception;
        }

        $this->connection = $connection;
    }

    public function disconnect()
    {
        $this->channels = [];

        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    private function getChannel(string $queue)
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

    public function send(string $queue, string $body)
    {
        $message = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);
        $this->getChannel($queue)->basic_publish($message, $queue);
    }

    public function consume(string $queue, int $timeout, $callback)
    {
        $channel = $this->getChannel($queue);

        $channel->basic_consume($queue, $this->consumerTag, false, false, false, false, $callback);

        try {
            while (count($channel->callbacks)) {
                $channel->wait($timeout);
            }
        } finally {
            $channel->basic_cancel($this->consumerTag);
            $channel->close();
            unset($this->channels[$queue]);
        }
    }
}
