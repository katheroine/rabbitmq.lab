<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const QUEUE_NAME = 'some_queue';

$connection = new AMQPStreamConnection(
    HOST,
    PORT,
    USERNAME,
    PASSWORD
);

$channel = $connection->channel();

$channel->queue_declare(
    queue: QUEUE_NAME,
    passive: false,
    durable: true,
    exclusive: false,
    auto_delete: false,
    nowait: false,
    arguments: null,
    ticket: null
);

$taskId = 0;

while ($taskId < 10)
{
    $taskId++;
    $messageBody = 'TASK #' . $taskId;
    $message = new AMQPMessage($messageBody);

    $channel->basic_publish(
        msg: $message,
        exchange: '',
        routing_key: QUEUE_NAME
    );

    print('SENT: ' . $messageBody . PHP_EOL);

    sleep(1);
}

$channel->close();
$connection->close();
