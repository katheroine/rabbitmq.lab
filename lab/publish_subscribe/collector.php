<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'other_exchange';

$connection = new AMQPStreamConnection(
    HOST,
    PORT,
    USERNAME,
    PASSWORD
);

$channel = $connection->channel();

list($queue_name, , ) = $channel->queue_declare(
    queue: '',
    passive: false,
    durable: false,
    exclusive: true,
    auto_delete: false,
    nowait: false,
    arguments: null,
    ticket: null
);

$callback = function($message) {
    print('RECEIVED: ' . $message->body . PHP_EOL);
    $delay = substr_count($message->body, '.');
    sleep($delay);
    $message->ack();
    print('DONE' . PHP_EOL);
};

$channel->basic_qos(
    prefetch_size: null,
    prefetch_count: 1,
    a_global: false
);

$channel->queue_bind(
    queue: $queue_name, // CHANGE
    exchange: EXCHANGE_NAME,
    routing_key: '',
    nowait: false,
    arguments: null,
    ticket: null
);

try {
    $channel->basic_consume(
        queue: $queue_name, // CHANGE
        consumer_tag: '',
        no_local: false,
        no_ack: false,
        exclusive: false,
        nowait: false,
        callback: $callback
    );

    while (count($channel->callbacks))
    {
        $channel->wait();
    }
} catch (\Throwable $exception) {
    echo $exception->getMessage();
}

$channel->close();
$connection->close();
