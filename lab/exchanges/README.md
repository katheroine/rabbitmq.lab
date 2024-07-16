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

There are a few exchange types available: **direct**, **topic**, **headers** and **fanout**. We'll focus on the last one -- the fanout. Let's create an exchange of this type, and call it logs:

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
