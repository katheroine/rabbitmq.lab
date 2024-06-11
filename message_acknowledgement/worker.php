<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

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

$callback = function($message) {
    print('RECEIVED: ' . $message->body . PHP_EOL);
    $delay = substr_count($message->body, '.');
    sleep($delay);
    $message->ack(); // CHANGE
    print('DONE' . PHP_EOL);
};

try {
    $channel->basic_consume(
        queue: QUEUE_NAME,
        consumer_tag: '',
        no_local: false,
        no_ack: false, // CHANGE
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
