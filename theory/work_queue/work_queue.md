# Work queue

## Why Is Queueing Necessary for PHP?

What happens when your application is receiving a lot of traffic, and much of that traffic requires **data processing** (e.g. *processing uploaded images or videos*, *crunching data*, *aggregating data* from multiple web services)? Often, the user submitting the data or information, or making a request for it, does not require it immediately — for example, if a mobile application is submitting information via an API, it may not need confirmation of completion, only that the request was accepted. How can you prevent these sorts of requests from tying up your web servers?

The common answer is to add **queueing** to your web application. Accept the data, queue it for later processing, and return a response immediately.

-- [Zend JobQueue](https://www.zend.com/blog/introducing-jobqueue)

## What is Work/Task Queue?

The main idea behind **Work Queues** (aka: **Task Queues**) is to avoid doing a resource-intensive task immediately and having to wait for it to complete. Instead we schedule the task to be done later. We encapsulate a task as a message and send it to a queue. A worker process running in the background will pop the tasks and eventually execute the job. When you run many workers the tasks will be shared between them.

-- [RabbitMQ Documentation](https://www.rabbitmq.com/tutorials/tutorial-two-php#work-queues)
