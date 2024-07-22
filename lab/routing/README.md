# Routing

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-four-php)

## Multiple bindings

It is perfectly legal to bind multiple queues with the same binding key.

## Emitting logs

We'll use this model for our logging system. Instead of fanout we'll send messages to a direct exchange. We will supply the log severity as a routing key. That way the receiving script will be able to select the severity it wants to receive. Let's focus on emitting logs first.

As always, we need to create an exchange first:

```php
$channel->exchange_declare('direct_logs', 'direct', false, false, false);
```

And we're ready to send a message:

```php
$channel->exchange_declare('direct_logs', 'direct', false, false, false);
$channel->basic_publish($msg, 'direct_logs', $severity);
```

To simplify things we will assume that 'severity' can be one of 'info', 'warning', 'error'.

## Subscribing

Receiving messages will work just like in the previous tutorial, with one exception - we're going to create a new binding for each severity we're interested in.

```php
foreach ($severities as $severity) {
    $channel->queue_bind($queue_name, 'direct_logs', $severity);
}
```

## Preparing emiter & collector

**emitter.php**

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'another_exchange';
const EXCHANGE_TYPE = 'direct';
// CHANGE
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
        routing_key: $severity // CHANGE
    );

    print('SENT: ' . $i . ' [' . $severity . ']' . PHP_EOL);
}

$channel->close();
$connection->close();

```

**collector.php**

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const EXCHANGE_NAME = 'another_exchange';
const EXCHANGE_TYPE = 'direct';
// CHANGE

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
    print('RECEIVED: [' . $message->getRoutingKey() . '] ' . $message->getBody() . PHP_EOL);
    $message->ack();
};

$channel->basic_qos(
    prefetch_size: null,
    prefetch_count: 1,
    a_global: false
);

$severities = array_slice($argv, 1);

foreach ($severities as $severity) {
    $channel->queue_bind(
        queue: $queue_name,
        exchange: EXCHANGE_NAME,
        routing_key: $severity, // CHANGE
        nowait: false,
        arguments: null,
        ticket: null
    );
}

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

```

## Running

**Running collector**

```bash
$ php collector.php warning error
```

**Running emitter**

```bash
$ php emitter.php 10
SENT: 1 [error]
SENT: 2 [error]
SENT: 3 [info]
SENT: 4 [error]
SENT: 5 [warning]
SENT: 6 [info]
SENT: 7 [warning]
SENT: 8 [warning]
SENT: 9 [info]
SENT: 10 [info]
```

**Observing receiver**

```bash
$ php collector.php
RECEIVED: [error] emitting 1: error
RECEIVED: [error] emitting 2: error
RECEIVED: [error] emitting 4: error
RECEIVED: [warning] emitting 5: warning
RECEIVED: [warning] emitting 7: warning
RECEIVED: [warning] emitting 8: warning
```
