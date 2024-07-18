<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'other_exchange';
const EXCHANGE_TYPE = 'fanout';


$connection = new AMQPStreamConnection(
    HOST,
    PORT,
    USERNAME,
    PASSWORD
);

$channel = $connection->channel();

// CHANGE

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

$messageBody = "Hi, there!";
$message = new AMQPMessage(
    $messageBody,
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

$channel->basic_publish(
    msg: $message,
    exchange: EXCHANGE_NAME,
    routing_key: '' // CHANGE
);

print('SENT' . PHP_EOL);

$channel->close();
$connection->close();
