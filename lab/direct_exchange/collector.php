<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'another_exchange';
const EXCHANGE_TYPE = 'direct';
const SEVERITIES = [
    'info',
    'warning',
    'error',
];

$connection = new AMQPStreamConnection(
    HOST,
    PORT,
    USERNAME,
    PASSWORD
);

$channel = $connection->channel();

$channel->exchange_declare(
    exchange: EXCHANGE_NAME,
    type: EXCHANGE_TYPE,
    passive: false,
    durable: false,
    auto_delete: true,
    internal: false,
    nowait: false,
    arguments: [],
    ticket: null
);

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
    print('RECEIVED: [' . $message->getRoutingKey() . '] ' . $message->getBody() . PHP_EOL); // CHANGE
    $message->ack();
};

$channel->basic_qos(
    prefetch_size: null,
    prefetch_count: 1,
    a_global: false
);

$channel->queue_bind(
    queue: $queue_name,
    exchange: EXCHANGE_NAME,
    routing_key: SEVERITIES[1],
    nowait: false,
    arguments: null,
    ticket: null
); // CHANGE

$channel->queue_bind(
    queue: $queue_name,
    exchange: EXCHANGE_NAME,
    routing_key: SEVERITIES[2],
    nowait: false,
    arguments: null,
    ticket: null
); // CHANGE

try {
    $channel->basic_consume(
        queue: $queue_name,
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
