<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const ROUTING_KEY = 'rpc_queue';
$correlation_id = uniqid();
global $response;
$response = null;

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

$callback = function($response_message) use ($correlation_id) {
    global $response;
    if ($response_message->get('correlation_id') == $correlation_id) {
        print('RECEIVED CLIENT: ' . $response_message->getBody() . PHP_EOL);
        $response = $response_message->getBody();
    }
};

$channel->basic_consume(
    queue: $queue_name,
    consumer_tag: '',
    no_local: false,
    no_ack: false,
    exclusive: false,
    nowait: false,
    callback: $callback
);

$message_body = "RPC";
$message = new AMQPMessage(
    $message_body,
    [
        'correlation_id' => $correlation_id,
        'reply_to' =>  $queue_name, // CHANGE
    ]
);

$channel->basic_publish(
    msg: $message,
    exchange: '',
    routing_key: ROUTING_KEY
);

print('SENT: ' . $message_body . PHP_EOL);

while (! $response) {
    $channel->wait();
}

$channel->close();
$connection->close();

echo('RESPONSE: ' . $response . PHP_EOL);
