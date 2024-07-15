# Round-robin dispatching

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-two-php#round-robin-dispatching)

One of the advantages of using a Task Queue is the ability to easily parallelise work. If we are building up a backlog of work, we can just add more workers and that way, scale easily.

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

$number = intval($argv[1]);

for ($i = 1; $i <= $number; $i++) {
    $times = rand(0, 5);
    $messageBody = "task {$i}: " . str_repeat('.', $times);
    $message = new AMQPMessage($messageBody);

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
$connection->close();

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

**Observing worker 1**

```bash
RECEIVED: task 1:
DONE
RECEIVED: task 3: ....
DONE
RECEIVED: task 5:
DONE
```

**Observing worker 2**

```bash
RECEIVED: task 2: ....
DONE
RECEIVED: task 4: ..
DONE
```

By default, RabbitMQ will send each message to the next consumer, in sequence. On average every consumer will get the same number of messages. This way of distributing messages is called **round-robin**. Try this out with three or more workers.
