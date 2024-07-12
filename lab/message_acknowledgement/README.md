# Message acknowledgement

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-two-php#message-acknowledgment)

## Introduction

Doing a task can take a few second, you may wonder what happens if *a consumer starts a long task and it terminates before it completes*. With our current code, once RabbitMQ delivers a message to the consumer, it immediately marks it for deletion. **In this case, if you terminate a worker, the message it was just processing is lost. The messages that were dispatched to this particular worker but were not yet handled are also lost**.

But we don't want to lose any tasks. If a worker dies, we'd like the task to be delivered to another worker.

In order to make sure a message is never lost, RabbitMQ supports **message acknowledgments**. *An ack(nowledgement) is sent back by the consumer to tell RabbitMQ that a particular message has been received, processed and that RabbitMQ is free to delete it.*

**If a consumer dies (its channel is closed, connection is closed, or TCP connection is lost) without sending an ack, RabbitMQ will understand that a message wasn't processed fully and will re-queue it. If there are other consumers online at the same time, it will then quickly redeliver it to another consumer.** That way you can be sure that no message is lost, even if the workers occasionally die.

A timeout (30 minutes by default) is enforced on consumer delivery acknowledgement. This helps detect buggy (stuck) consumers that never acknowledge deliveries. You can increase this timeout as described in Delivery Acknowledgement Timeout.

Message acknowledgments were previously turned off by ourselves. It's time to turn them on by setting the fourth parameter to `basic_consume` to `false` (`true` means *no ack*) and send a proper acknowledgment from the worker, once we're done with a task.

## Preparing task & worker

**task.php**

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

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

$messageBody = implode(' ', array_slice($argv, 1));

$message = new AMQPMessage($messageBody);

$channel->basic_publish(
    msg: $message,
    exchange: '',
    routing_key: QUEUE_NAME
);

print('SENT: ' . $messageBody . PHP_EOL);

$channel->close();
$connection->close();

```

**worker.php**

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

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
    print('DONE' . PHP_EOL);
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

$channel->close();

```

## Running

**Runnung worker 1**

```bash
$ php worker.php
```

**Runnung worker 2**

```bash
$ php worker.php
```

**Running task**

```bash
$ php task.php 5
SENT: 1
SENT: 2
SENT: 3
SENT: 4
SENT: 5
```

**Observing and terminating worker 1**

```bash
$ php worker.php
RECEIVED: task 1: ..........
DONE
RECEIVED: task 3: ..........
```

**Observing worker 2**

```bash
$ php worker.php
RECEIVED: task 2: ..........
DONE
RECEIVED: task 4: ..........
DONE
```

## Modifyig worker

**worker.php**

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

```

## Running

**Running worker 1**

```bash
$ php worker.php
```

**Running worker 2**

```bash
$ php worker.php
```

**Running task**

```bash
$ php task.php 5
SENT: 1
SENT: 2
SENT: 3
SENT: 4
SENT: 5
```

**Observing and terminating worker 1**

```bash
$ php worker.php
RECEIVED: task 1: ..........
DONE
RECEIVED: task 3: ..........
```

**Observing worker 2**

```bash
RECEIVED: task 2: ........
DONE
RECEIVED: task 4: .......
DONE
RECEIVED: task 3: ..........
DONE
RECEIVED: task 5: .......
DONE
```

Using this code, you can ensure that even if you terminate a worker using CTRL+C while it was processing a message, nothing is lost. Soon after the worker terminates, all unacknowledged messages are redelivered.
