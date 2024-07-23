# Topic exchange

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-five-php)

Although using the direct exchange improved our system, it still has limitations - it can't do routing based on multiple criteria.

In our logging system we might want to subscribe to not only logs based on severity, but also based on *the source which emitted the log*. You might know this concept from the syslog unix tool, which routes logs based on both severity (`info/warn/crit...`) and facility (`auth/cron/kern...`).

That would give us a lot of flexibility - we may want to listen to just critical errors coming from 'cron' but also all logs from 'kern'.

To implement that in our logging system we need to learn about a more complex topic exchange.

## Topic exchange

Messages sent to a topic exchange can't have an arbitrary routing_key - it must be a list of words, delimited by dots. The words can be anything, but usually they specify some features connected to the message. A few valid routing key examples: "stock.usd.nyse", "nyse.vmw", "quick.orange.rabbit". There can be as many words in the routing key as you like, up to the limit of 255 bytes.

The binding key must also be in the same form. The logic behind the topic exchange is similar to a direct one - a message sent with a particular routing key will be delivered to all the queues that are bound with a matching binding key. However there are two important special cases for binding keys:

* `*` (star) can substitute for exactly one word.
* `#` (hash) can substitute for zero or more words.

Topic exchange is powerful and can behave like other exchanges.

When a queue is bound with "#" (hash) binding key - it will receive all the messages, regardless of the routing key - like in fanout exchange.

When special characters "*" (star) and "#" (hash) aren't used in bindings, the topic exchange will behave just like a direct one.

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
const EXCHANGE_NAME = 'next_exchange';
const EXCHANGE_TYPE = 'topic'; // CHANGE

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

$binding_keys = array_slice($argv, 1);

foreach ($binding_keys as $binding_key) {
    $channel->queue_bind(
        queue: $queue_name,
        exchange: EXCHANGE_NAME,
        routing_key: $binding_key, // CHANGE
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

### Strict match

**Running collector**

```bash
$ php collector.php "lib.error"
```

**Running emitter**

```bash
$ php emitter.php 5
SENT: 1 [lib - error]
SENT: 2 [lib - error]
SENT: 3 [lib - error]
SENT: 4 [kernel - error]
SENT: 5 [lib - warning]
```

**Observing receiver**

```bash
RECEIVED: [lib.error] emitting 1: lib - error
RECEIVED: [lib.error] emitting 2: lib - error
RECEIVED: [lib.error] emitting 3: lib - error
```

### Partial match

**Running collector**

```bash
$ php collector.php "*.error"
```

**Running emitter**

```bash
$ php emitter.php 5
SENT: 1 [lib - error]
SENT: 2 [module - info]
SENT: 3 [module - error]
SENT: 4 [lib - error]
SENT: 5 [lib - warning]
```

**Observing receiver**

```bash
RECEIVED: [lib.error] emitting 1: lib - error
RECEIVED: [module.error] emitting 3: module - error
RECEIVED: [lib.error] emitting 4: lib - error
```

### No filter

**Running collector**

```bash
$ php collector.php "#"
```

**Running emitter**

```bash
$ php emitter.php 5
SENT: 1 [lib - error]
SENT: 2 [module - error]
SENT: 3 [lib - error]
SENT: 4 [kernel - error]
SENT: 5 [app - warning]
```

**Observing receiver**

```bash
RECEIVED: [lib.error] emitting 1: lib - error
RECEIVED: [module.error] emitting 2: module - error
RECEIVED: [lib.error] emitting 3: lib - error
RECEIVED: [kernel.error] emitting 4: kernel - error
RECEIVED: [app.warning] emitting 5: app - warning
```
