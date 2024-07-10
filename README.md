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

### Lab

1. [Simple queue](./lab/simple_queue/README.md)
2. [Time-consuming task](./lab/time_consuming_task/README.md)
3. [Message acknowledgment](./lab/message_acknowledgement/README.md)
4. [Fair dispatch](./lab/fair_dispatch/README.md)
