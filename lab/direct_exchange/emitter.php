<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'another_exchange';
const EXCHANGE_TYPE = 'direct'; // CHANGE
const BINDING_KEY = 'some_key'; // CHANGE
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

$number = intval($argv[1]);

for ($i = 1; $i <= $number; $i++) {
    $severity = SEVERITIES[rand(0, 2)];
    $messageBody = "emitting {$i}: " . $severity;
    $message = new AMQPMessage(
        $messageBody,
        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
    );

    $channel->basic_publish(
        msg: $message,
        exchange: EXCHANGE_NAME,
        routing_key: BINDING_KEY, // CHANGE
    );

    print('SENT: ' . $i . ' [' . $severity . ']' . PHP_EOL);
}

$channel->close();
$connection->close();
