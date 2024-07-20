# Direct exchange

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-four-php#direct-exchange)

## Bindings

In previous examples we were already creating **bindings**. You may recall code like:

```php
$channel->queue_bind($queue_name, 'logs');
```

A *binding* is a relationship between an *exchange* and a *queue*. This can be simply read as: the *queue* is interested in messages from this *exchange*.

Bindings can take an extra `routing_key` parameter. To avoid the confusion with a `$channel::basic_publish` parameter we're going to call it a *binding key*. This is how we could create a binding with a key:

```php
$binding_key = 'black';
$channel->queue_bind($queue_name, $exchange_name, $binding_key);
```

The meaning of a *binding key* depends on the *exchange type*. The *fanout exchanges*, which we used previously, simply ignored its value.

## Direct exchange

Our logging system from the previous tutorial broadcasts all messages to all consumers. We want to extend that to allow filtering messages based on their severity. For example we may want the script which is writing log messages to the disk to only receive critical errors, and not waste disk space on warning or info log messages.

We were using a *fanout exchange*, which doesn't give us much flexibility - it's only capable of mindless broadcasting.

We will use a *direct exchange* instead. The routing algorithm behind a direct exchange is simple - a message goes to the queues whose binding key exactly matches the routing key of the message.

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
    $severity = SEVERITIES[rand(0, 2)]; // CHANGE
    $messageBody = "emitting {$i}: " . $severity; // CHANGE
    $message = new AMQPMessage(
        $messageBody,
        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
    );

    $channel->basic_publish(
        msg: $message,
        exchange: EXCHANGE_NAME,
        routing_key: $severity // CHANGE
    );

    print('SENT: ' . $i . ' [' . $severity . ']' . PHP_EOL); // CHANGE
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

```

## Running

**Running collector**

```bash
$ php collector.php
```

**Running emitter**

```bash
$ $ php emitter.php 10
SENT: 1 [error]
SENT: 2 [error]
SENT: 3 [warning]
SENT: 4 [info]
SENT: 5 [warning]
SENT: 6 [info]
SENT: 7 [warning]
SENT: 8 [error]
SENT: 9 [info]
SENT: 10 [warning]
```

**Observing receiver**

```bash
$ php collector.php
RECEIVED: [error] emitting 1: error
RECEIVED: [error] emitting 2: error
RECEIVED: [warning] emitting 3: warning
RECEIVED: [warning] emitting 5: warning
RECEIVED: [warning] emitting 7: warning
RECEIVED: [error] emitting 8: error
RECEIVED: [warning] emitting 10: warning
```
