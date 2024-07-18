# Exchanges

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-three-php#exchanges)

In previous parts of the tutorial we sent and received messages to and from a queue. Now it's time to introduce the full messaging model in Rabbit.

Let's quickly go over what we covered in the previous tutorials:

A **producer** is a user application that *sends messages*.
A **queue** is a buffer that *stores messages*.
A **consumer** is a user application that *receives messages*.

The core idea in the messaging model in RabbitMQ is that the producer never sends any messages directly to a queue. Actually, quite often the producer doesn't even know if a message will be delivered to any queue at all.
Instead, the producer can only send messages to an exchange.
---

An **exchange** is a very simple thing. On one side it *receives messages from producers* and the other side it *pushes them to queues*. The exchange must know exactly what to do with a message it receives. Should it be appended to a particular queue? Should it be appended to many queues? Or should it get discarded. The rules for that are defined by the exchange type.

There are a few exchange types available: **direct**, **topic**, **headers** and **fanout**. Let's create an exchange of `fanout` type, and call it logs:

```php
$channel->exchange_declare('logs', 'fanout', false, false, false);
```

The fanout exchange is very simple. As you can probably guess from the name, it just broadcasts all the messages it receives to all the queues it knows. And that's exactly what we need for our logger.

## The default exchange

In previous parts of the tutorial we knew nothing about exchanges, but still were able to send messages to queues. That was possible because we were using a default exchange, which we identify by the empty string (`""`).

Recall how we published a message before:

```php
$channel->basic_publish($msg, '', 'hello');
```

Here we use the default or nameless exchange: messages are routed to the queue with the name specified by `routing_key`, if it exists. The *routing key* is the third argument to `basic_publish`.

Now, we can publish to our named exchange instead:

```php
$channel->exchange_declare('logs', 'fanout', false, false, false);
$channel->basic_publish($msg, 'logs');
```

## Bindings

Now we need to tell the exchange to send messages to our queue. That relationship between exchange and a queue is called a binding.

```php
$channel->queue_bind($queue_name, 'logs');
```

From now on the logs exchange will append messages to our queue.

## Preparing emiter & receiver

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
const QUEUE_NAME = 'other_queue';
const EXCHANGE_NAME = 'some_exchange';
const EXCHANGE_TYPE = 'fanout';


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
); // CHANGE

$messageBody = "Hi, there!";
$message = new AMQPMessage(
    $messageBody,
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

$channel->basic_publish(
    msg: $message,
    exchange: EXCHANGE_NAME, // CHANGE
    routing_key: QUEUE_NAME
);

print('SENT' . PHP_EOL);

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
const QUEUE_NAME = 'other_queue';
const EXCHANGE_NAME = 'some_exchange';

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
);

$channel->queue_bind(
    queue: QUEUE_NAME,
    exchange: EXCHANGE_NAME
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
$connection->close();

```

## Running

**Running collector**

```bash
$ php collector.php
```

**Running emitter**

```bash
$ php emitter.php
SENT
```

**Observing receiver**

```bash
RECEIVED: Hi, there!
DONE
```
