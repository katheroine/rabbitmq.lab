<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'next_exchange';
const EXCHANGE_TYPE = 'topic'; // CHANGE
const SUBJECTS = [
    'kernel',
    'module',
    'lib',
    'app',
];
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
    auto_delete: false,
    internal: false,
    nowait: false,
    arguments: [],
    ticket: null
);

$number = intval($argv[1]);

for ($i = 1; $i <= $number; $i++) {
    $subject = SUBJECTS[rand(0, 3)];
    $severity = SEVERITIES[rand(0, 2)];
    $messageBody = "emitting {$i}: " . $subject . ' - ' . $severity;
    $message = new AMQPMessage(
        $messageBody,
        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
    );

    $channel->basic_publish(
        msg: $message,
        exchange: EXCHANGE_NAME,
        routing_key: $subject . '.' . $severity // CHANGE
    );

    print('SENT: ' . $i . ' [' . $subject . ' - ' . $severity . ']' . PHP_EOL);
}

$channel->close();
$connection->close();
