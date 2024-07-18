# RabbitMQ.lab

Laboratory of RabbitMQ.

## Preparing

**Installing `php-amqp`**

```bash
$ sudo aptitude install php-amqp
```

**Installing `rabbitmq-server`**

```bash
$ sudo aptitude install rabbitmq-server
```

**`composer.json`**

```composer
{
    "require": {
        "php-amqplib/php-amqplib": "^3.5"
    }
}
```

**Dependencies installing**

`$ composer install`

## Index

### Theory

1. [Message broker](./theory/message_broker/message_broker.md)
2. [Work queue](./theory/work_queue/work_queue.md)

### Lab

1. [Simple queue](./lab/simple_queue/README.md)
2. [Work queues](./lab/work_queues/README.md)
3. [Round-robin dispatching](./lab/round_robin_dispatching/README.md)
4. [Message acknowledgment](./lab/message_acknowledgement/README.md)
5. [Message durability](./lab/message_durability/README.md)
6. [Fair dispatch](./lab/fair_dispatch/README.md)
7. [Exchanges](./lab/exchanges/README.md)
8. [Publish-subscribe](./lab/publish_subscribe/README.md)
