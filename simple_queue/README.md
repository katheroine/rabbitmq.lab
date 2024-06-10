# Simple queue

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-one-php)

## Introduction

**RabbitMQ** is a message broker: it accepts and forwards messages.

RabbitMQ, and messaging in general, uses some jargon.

**Producing** means nothing more than *sending*. A program that sends messages is a **producer**.

A **queue** is the name for the *post box* in RabbitMQ. Although messages flow through RabbitMQ and your applications, they can only be stored inside a queue. A queue is only bound by the host's memory & disk limits, it's essentially a large message buffer.

**Consuming** has a similar meaning to receiving. A **consumer** is a program that mostly waits to receive messages.

Many producers can send messages that go to one queue, and many consumers can try to receive data from one queue.

Note that the producer, consumer, and broker do not have to reside on the same host; indeed in most applications they don't. An application can be both a producer and consumer, too.

## Preparing producer & consumer

**producer.php**

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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

$channel->queue_declare(
    queue: QUEUE_NAME,
    passive: false,
    durable: true,
    exclusive: false,
    auto_delete: false,
    nowait: false,
    arguments: null,
    ticket: null
);

$taskId = 0;

while ($taskId < 10)
{
    $taskId++;
    $messageBody = 'TASK #' . $taskId;
    $message = new AMQPMessage($messageBody);

    $channel->basic_publish(
        msg: $message,
        exchange: '',
        routing_key: QUEUE_NAME
    );

    print('SENT: ' . $messageBody . PHP_EOL);

    sleep(1);
}

$channel->close();
$connection->close();

```

**consumer.php**

```php
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
    sleep(1);
};

try {
    $channel->basic_consume(
        queue: QUEUE_NAME,
        consumer_tag: '',
        no_local: false,
        no_ack: true,
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

```

## Running

**Running consumer**

```bash
$ php consumer.php

```

**Running producer**

```bash
$ php producer.php
SEND: TASK #1
SEND: TASK #2
SEND: TASK #3
SEND: TASK #4
SEND: TASK #5
SEND: TASK #6
SEND: TASK #7
SEND: TASK #8
SEND: TASK #9
SEND: TASK #10
```

**Observing consumer**

```bash
RECEIVED: TASK #1
RECEIVED: TASK #2
RECEIVED: TASK #3
RECEIVED: TASK #4
RECEIVED: TASK #5
RECEIVED: TASK #6
RECEIVED: TASK #7
RECEIVED: TASK #8
RECEIVED: TASK #9
RECEIVED: TASK #10
```

![Browser monitor](simple_queue_-_browser_monitor.png "Browser monitor")
