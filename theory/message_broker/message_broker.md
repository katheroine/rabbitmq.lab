# Message broker

A **message broker** (also known as an **integration broker** or **interface engine**) is an *intermediary computer program module* that translates a message from the *formal messaging protocol of the sender* to the *formal messaging protocol of the receiver*.

Message brokers are elements in telecommunication or computer networks where software applications communicate by exchanging formally-defined messages. Message brokers are a building block of **message-oriented middleware (MOM)** but are typically not a replacement for traditional middleware like MOM and **remote procedure call (RPC)**.

A message broker is an *architectural pattern for message validation, transformation, and routing*. It mediates communication among applications, minimizing the mutual awareness that applications should have of each other in order to be able to exchange messages, effectively implementing decoupling.

The primary purpose of a broker is *to take incoming messages from applications and perform some action on them*. Message brokers can decouple end-points, meet specific non-functional requirements, and facilitate reuse of intermediary functions. For example, a message broker may be used to *manage a workload queue or message queue for multiple receivers*, *providing reliable storage*, *guaranteed message delivery and perhaps transaction management*.

The following represent other examples of actions that might be handled by the broker:

* Route messages to one or more destinations
* Transform messages to an alternative representation
* Perform message aggregation, decomposing messages into multiple messages and sending them to their destination, then recomposing the responses into one message to return to the user
* Interact with an external repository to augment a message or store it
* Invoke web services to retrieve data
* Respond to events or errors
* Provide content and topic-based message routing using the publish–subscribe pattern

Message brokers are generally based on one of two fundamental architectures: **hub-and-spoke** and **message bus**. In the first, a central server acts as the mechanism that provides integration services, whereas with the latter, the message broker is a communication backbone or distributed service that acts on the bus. Additionally, a more scalable multi-hub approach can be used to integrate multiple brokers.

-- [Wikipedia](https://en.wikipedia.org/wiki/Message_broker)
