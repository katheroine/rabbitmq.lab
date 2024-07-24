# Remote procedure call (RPC)

[Official documentation](https://www.rabbitmq.com/tutorials/tutorial-six-php)

We learned how to use **Work Queues** to *distribute time-consuming tasks among multiple workers*.

But what if we need to run a function on a remote computer and wait for the result? Well, that's a different story. This pattern is commonly known as **Remote Procedure Call** or **RPC**.

In this tutorial we're going to use RabbitMQ to build an RPC system: a client and a scalable RPC server. As we don't have any time-consuming tasks that are worth distributing, we're going to create a dummy RPC service that returns Fibonacci numbers.

## Client interface​

To illustrate how an RPC service could be used we're going to create a simple client class. It's going to expose a method named call which sends an RPC request and blocks until the answer is received:

$fibonacci_rpc = new FibonacciRpcClient();
$response = $fibonacci_rpc->call(30);
echo ' [.] Got ', $response, "\n";

A note on RPC
Although RPC is a pretty common pattern in computing, it's often criticised. The problems arise when a programmer is not aware whether a function call is local or if it's a slow RPC. Confusions like that result in an unpredictable system and adds unnecessary complexity to debugging. Instead of simplifying software, misused RPC can result in unmaintainable spaghetti code.

Bearing that in mind, consider the following advice:

Make sure it's obvious which function call is local and which is remote.
Document your system. Make the dependencies between components clear.
Handle error cases. How should the client react when the RPC server is down for a long time?
When in doubt avoid RPC. If you can, you should use an asynchronous pipeline - instead of RPC-like blocking, results are asynchronously pushed to a next computation stage.

Callback queue
In general doing RPC over RabbitMQ is easy. A client sends a request message and a server replies with a response message. In order to receive a response we need to send a 'callback' queue address with the request. We can use the default queue. Let's try it:

list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);

$msg = new AMQPMessage(
    $payload,
    array('reply_to' => $queue_name)
);

$channel->basic_publish($msg, '', 'rpc_queue');

# ... then code to read a response message from the callback_queue ...

Message properties
The AMQP 0-9-1 protocol predefines a set of 14 properties that go with a message. Most of the properties are rarely used, with the exception of the following:

delivery_mode: Marks a message as persistent (with a value of 2) or transient (1). You may remember this property from the second tutorial.
content_type: Used to describe the mime-type of the encoding. For example for the often used JSON encoding it is a good practice to set this property to: application/json.
reply_to: Commonly used to name a callback queue.
correlation_id: Useful to correlate RPC responses with requests.
Correlation Id
In the method presented above we suggest creating a callback queue for every RPC request. That's pretty inefficient, but fortunately there is a better way - let's create a single callback queue per client.

That raises a new issue, having received a response in that queue it's not clear to which request the response belongs. That's when the correlation_id property is used. We're going to set it to a unique value for every request. Later, when we receive a message in the callback queue we'll look at this property, and based on that we'll be able to match a response with a request. If we see an unknown correlation_id value, we may safely discard the message - it doesn't belong to our requests.

You may ask, why should we ignore unknown messages in the callback queue, rather than failing with an error? It's due to a possibility of a race condition on the server side. Although unlikely, it is possible that the RPC server will die just after sending us the answer, but before sending an acknowledgment message for the request. If that happens, the restarted RPC server will process the request again. That's why on the client we must handle the duplicate responses gracefully, and the RPC should ideally be idempotent.
