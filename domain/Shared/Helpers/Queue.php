<?php

namespace Domain\Shared\Helpers;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionException;
use ReflectionMethod;

final class Queue
{
    protected static $isBooted = false;
    protected static $connection;
    protected static $channel;
    protected static $currentqueue;
    protected static $consumerCounts = [];
    protected static $dirty = false;
    protected static $dirtyCount = 0;

    private static function boot()
    {
        if (self::$isBooted) {
            return false;
        }

        # Connection and channel failures are logged, not thrown: a broker that is down
        # must not take the caller with it.
        try {
            self::connect();
        } catch (\Throwable $th) {
            Log::error('Queue -> failed to connect', ['code' => $th->getCode(), 'message' => $th->getMessage()]);
        }
        try {
            self::getChannel();
        } catch (\Throwable $th) {
            Log::error('Queue -> failed to get channel', ['code' => $th->getCode(), 'message' => $th->getMessage()]);
        }

        self::$isBooted = true;

        return true;
    }

    /**
     * Publishes a deferred call: the consumer resolves $class::$method(...$args) by reflection.
     */
    public static function publish(string $queue, string $class, string $method, ...$args): void
    {
        $message = self::parseMessage([
            '__RID' => Hash::make(Str::random(10)),
            '__class' => $class,
            '__method' => $method,
            '__args' => $args,
            '__publishedAt' => Carbon::now(),
            '__messageID' => Str::uuid(),
        ]);

        self::directPublish($queue, $message);
    }

    private static function connect(): void
    {
        self::$connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USER'),
            env('RABBITMQ_PASSWORD')
        );
    }

    private static function getChannel(): void
    {
        self::$channel = self::$connection->channel();
    }

    private static function parseMessage(array $message): AMQPMessage
    {
        # delivery_mode 2 persists the message to disk, so it survives a broker restart.
        return new AMQPMessage(serialize($message), [
            'content-type' => 'application/php-serialized',
            'delivery_mode' => 2,
        ]);
    }

    public static function directPublish(string $queue, AMQPMessage $message): void
    {
        self::boot();
        self::declareQueue($queue);
        self::$channel->basic_publish($message, '', $queue);
        self::$dirty = true;
        self::$dirtyCount++;
    }

    private static function declareQueue(string $queue): void
    {
        # Redeclaring the queue on every message is wasteful, so the last one is cached.
        if (self::$currentqueue === $queue) {
            return;
        }

        $returnedDeclare = self::$channel->queue_declare(
            $queue,
            false,   # passive: do not fail when the queue is missing
            true,    # durable: survives a broker restart
            false,   # exclusive: other consumers may read this queue
            false    # auto_delete: keep the queue when the last consumer leaves
        );

        self::$consumerCounts[$returnedDeclare[0] ?? ''] = $returnedDeclare[2] ?? 0;
        self::$currentqueue = $queue;
    }

    public static function setQos(int $prefetchSize, int $prefetchCount, bool $aGlobal)
    {
        self::boot();

        # A prefetchSize of 0 means no byte limit on the messages sent to the consumer.
        self::$channel->basic_qos($prefetchSize, $prefetchCount, $aGlobal);
    }

    public static function getConsumerCount(string $queue): int
    {
        self::boot();
        self::declareQueue($queue);

        return self::$consumerCounts[$queue] ?? 0;
    }

    public static function commit()
    {
        self::boot();

        if (self::$dirty) {
            self::$channel->publish_batch();
            self::$dirty = false;
            self::$dirtyCount = 0;
        }
    }

    /**
     * Returns '__delay__' when the message is not due yet, so the caller can requeue it.
     */
    public static function processMessage(AMQPMessage $message, int $delay = 0)
    {
        $messageBody = unserialize($message->getBody());
        $class = Arr::get($messageBody, '__class');
        $method = Arr::get($messageBody, '__method');
        $args = Arr::get($messageBody, '__args');
        $publishedAt = Arr::get($messageBody, '__publishedAt');

        if ($publishedAt && $delay) {
            $processAt = $publishedAt->addSeconds($delay);
            $now = Carbon::now();

            if ($processAt->isAfter($now)) {
                return '__delay__';
            }
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
        } catch (ReflectionException $re) {
            Log::warning(
                __CLASS__ . '.' . __FUNCTION__ . ": \"{$re->getMessage()}\"",
                [
                    'messageBody' => $messageBody,
                    'class' => $class,
                    'function' => $method,
                ]
            );

            return false;
        }

        $classInstance = null;
        if (!$reflection->isStatic()) {
            $classInstance = new $class;
        }

        return $reflection->invoke($classInstance, ...$args);
    }

    /**
     * Blocks the process while the channel keeps consuming.
     */
    public static function consume(string $queue, Closure $closure): void
    {
        self::boot();
        self::declareQueue($queue);

        self::$channel->basic_consume(
            $queue,
            '',     # consumer tag: empty lets the broker generate a unique one
            false,  # no_local
            false,  # no_ack: the callback acknowledges the message itself
            false,  # exclusive: other consumers may read this queue
            false,  # nowait
            function ($message) use ($closure) {
                $closure($message);
            }
        );

        while (self::$channel->is_consuming()) {
            self::$channel->wait();
        }
    }

    public static function testConsume($data)
    {
        Log::info('TESTING_QUEUE_CONSUME', $data);
    }
}
