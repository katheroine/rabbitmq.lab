# Fair dispatch

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-two-php#fair-dispatch)

*In a situation with two workers, when all odd messages are heavy and even messages are light, one worker will be constantly busy and the other one will do hardly any work.* RabbitMQ doesn't know anything about that and will still dispatch messages evenly.

This happens because RabbitMQ just dispatches a message when the message enters the queue. It doesn't look at the number of unacknowledged messages for a consumer. It just blindly dispatches every n-th message to the n-th consumer.

In order to defeat that we can use the basic_qos method with the prefetch_count = 1 setting. This tells RabbitMQ not to give more than one message to a worker at a time. Or, in other words, don't dispatch a new message to a worker until it has processed and acknowledged the previous one. Instead, it will dispatch it to the next worker that is not still busy.

If all the workers are busy, your queue can fill up. You will want to keep an eye on that, and maybe add more workers, or have some other strategy.

## Modifyig worker

**task.php**

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
    $message->ack();
    print('DONE' . PHP_EOL);
};

$channel->basic_qos(
    prefetch_size: null,
    prefetch_count: 1,
    a_global: false
); // CHANGE

try {
    $channel->basic_consume(
        queue: QUEUE_NAME,
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

**Running tasks**

```bash
$ php task.php Lorem ipsum............
SENT: Lorem ipsum............
$ php task.php Lorem ipsum.
SENT: Lorem ipsum.
$ php task.php Lorem ipsum............
SENT: Lorem ipsum............
$ php task.php Lorem ipsum.
SENT: Lorem ipsum.
```

**Observing worker 1**

```bash
$ php worker.php
RECEIVED: Lorem ipsum............
DONE
```

**Observing worker 2**

```bash
$ php worker.php
RECEIVED: Lorem ipsum.
DONE
RECEIVED: Lorem ipsum............
DONE
RECEIVED: Lorem ipsum.
DONE
```
