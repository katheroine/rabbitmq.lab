# Message durability

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-two-php#message-durability)

We have learned how to make sure that even if the consumer dies, the task isn't lost. But our tasks will still be lost if RabbitMQ server stops.

When RabbitMQ quits or crashes it will forget the queues and messages unless you tell it not to. Two things are required to make sure that messages aren't lost: we need to mark both the queue and messages as durable.

First, we need to make sure that the queue will survive a RabbitMQ node restart. In order to do so, we need to declare it as durable. To do so we pass the third parameter to `queue_declare` as `true`:

`php $channel->queue_declare('other_queue', false, true, false, false);`

It must be a newly createt queue. (RabbitMQ doesn't allow you to redefine an existing queue with different parameters and will return an error to any program that tries to do that.)

At this point we're sure that the task_queue queue won't be lost even if RabbitMQ restarts. Now we need to mark our messages as persistent

by setting the delivery_mode = 2 message property which AMQPMessage takes as part of the property array.

```php
$message = new AMQPMessage(
    $messageBody,
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);
```

*Marking messages as persistent doesn't fully guarantee that a message won't be lost. Although it tells RabbitMQ to save the message to disk, there is still a short time window when RabbitMQ has accepted a message and hasn't saved it yet. Also, RabbitMQ doesn't do `fsync(2)` for every message -- it may be just saved to cache and not really written to the disk. The persistence guarantees aren't strong, but it's more than enough for our simple task queue. If you need a stronger guarantee then you can use [**publisher confirms**](https://www.rabbitmq.com/docs/confirms).*

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
    durable: false,
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
$connection->close();

```

## Running

**Running task**

```bash
$ php task.php 5
SENT: 1
SENT: 2
SENT: 3
SENT: 4
SENT: 5
```

**Running worker**

```bash
$ php worker.php
```

**Observing worker**

```bash
RECEIVED: task 1: .....
DONE
RECEIVED: task 2: .....
DONE
RECEIVED: task 3: ......
```

**Stopping server**

```bash
$ sudo service rabbitmq-server stop
```

**Observing worker**

```bash
Connection reset by peerPHP Fatal error:  Uncaught PhpAmqpLib\Exception\AMQPConnectionClosedException: Broken pipe or closed connection
```

**Starting server**

```bash
$ sudo service rabbitmq-server start
```

**Re-running and observing worker**

```bash
$ php worker.php
NOT_FOUND - no queue 'some_queue' in vhost '/'
```

## Modifyig task & worker

**taks.php**

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

const HOST = 'localhost';
const PORT = '5672';
const USERNAME = 'guest';
const PASSWORD = 'guest';
const QUEUE_NAME = 'other_queue'; // change

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
    durable: true, // change
    exclusive: false,
    auto_delete: false,
    nowait: false,
    arguments: null,
    ticket: null
);

$number = intval($argv[1]);

for ($i = 1; $i <= $number; $i++) {
    $times = rand(5, 10);
    $messageBody = "task {$i}: " . str_repeat('.', $times);
    $message = new AMQPMessage(
        $messageBody,
        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT] // change
    );

    $channel->basic_publish(
        msg: $message,
        exchange: '',
        routing_key: QUEUE_NAME
    );

    print('SENT: ' . $i . PHP_EOL);
}

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
const QUEUE_NAME = 'other_queue'; // change

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
$connection->close();

```

## Running

**Running task**

```bash
$ php task.php 5
SENT: 1
SENT: 2
SENT: 3
SENT: 4
SENT: 5
```

**Running worker**

```bash
$ php worker.php
```

**Observing worker**

```bash
RECEIVED: task 1: .....
DONE
RECEIVED: task 2: .....
DONE
RECEIVED: task 3: ......
```

**Stopping server**

```bash
$ sudo service rabbitmq-server stop
```

**Observing worker**

```bash
Connection reset by peerPHP Fatal error:  Uncaught PhpAmqpLib\Exception\AMQPConnectionClosedException: Broken pipe or closed connection
```

**Starting server**

```bash
$ sudo service rabbitmq-server start
```

**Re-running and observing worker**

```bash
RECEIVED: task 3: .........
DONE
RECEIVED: task 4: ........
DONE
RECEIVED: task 5: .......
DONE
```
