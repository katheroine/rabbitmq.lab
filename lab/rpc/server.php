<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const ROUTING_KEY = 'rpc_queue';

$connection = new AMQPStreamConnection(
    HOST,
    PORT,
    USERNAME,
    PASSWORD
);

$channel = $connection->channel();

$queue = $channel->queue_declare(
    queue: ROUTING_KEY,
    passive: false,
    durable: false,
    exclusive: false,
    auto_delete: false,
    nowait: false,
    arguments: [],
    ticket: null
);

$callback = function($request_message) {
    print('RECEIVED SERVER: [' . $request_message->getRoutingKey() . '] ' . $request_message->getBody() . PHP_EOL);
    $response_message = new AMQPMessage(
        $request_message->getBody() . '+',
        ['correlation_id' => $request_message->get('correlation_id')]
    );

    $request_message->getChannel()->basic_publish(
        $response_message,
        '',
        $request_message->get('reply_to')
    );
    $request_message->ack();
};

$channel->basic_qos(
    prefetch_size: null,
    prefetch_count: 1,
    a_global: false
);

$channel->basic_consume(
    queue: ROUTING_KEY,
    consumer_tag: '',
    no_local: false,
    no_ack: false,
    exclusive: false,
    nowait: false,
    callback: $callback
);

try {
    $channel->consume();
} catch (\Throwable $exception) {
    echo $exception->getMessage();
}

$channel->close();
$connection->close();
