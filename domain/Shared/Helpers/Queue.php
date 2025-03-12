<?php

namespace Domain\Shared\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class Queue 
{
    protected static $isBooted = false;
    protected static $connection;
    protected static $channel;
    protected static $currentBindingKey;
    protected static $consumerCounts = [];
    protected static $dirty = false;
    protected static $dirtyCount = 0;

    private static function boot()
    {
        if (self::$isBooted) {
            return false;
        }

        try {
            self::connect();
        } catch (\Throwable $th) {
            Log::error('Queue -> failed to connect', ['code' => $th->getCode(), 'message' => $th->getMessage()]);
        }
        try {
            self::openChannel();
        } catch (\Throwable $th) {
            Log::error('Queue -> failed to get channel', ['code' => $th->getCode(), 'message' => $th->getMessage()]);
        }

        self::$isBooted = true;

        return true;
    }

    
    public static function publish(string $bindingKey, string $class, string $method, ...$args)
    {
        $publishedAt = Carbon::now();
        $messageID = Str::uuid();
        
        $message = self::parseMessage([
            '__RID' => Hash::make(Str::random(10)),
            '__class' => $class,
            '__method' => $method,
            '__args' => $args,
            '__publishedAt' => $publishedAt,
            '__messageID' => $messageID,
        ]);

        self::directPublish($bindingKey, $message);
    }
    
    private static function connect()
    {
        self::$connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USER'),
            env('RABBITMQ_PASSWORD'),
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            10,
            10,
            null,
            true,
            0,
            0,
            null
        );
    }
    
    private static function openChannel(): void
    {
        self::$channel = self::$connection->channel();
    }
    
    private static function parseMessage(array $message): AMQPMessage
    {
        return new AMQPMessage(serialize($message), [
            'content-type' => 'application/php-serialized',
            'delivery_mode' => 2,
        ]);
    }
    
    
    private static function directPublish(string $bindingKey, AMQPMessage $message): bool
    {
        self::boot();
        self::declareBindingKey($bindingKey);
        self::$channel->basic_publish($message, '', $bindingKey);
        self::$dirty = true;
        self::$dirtyCount++;
    
        return true;
    }

    private static function declareBindingKey(string $bindingKey): void
    {
        if (self::$currentBindingKey === $bindingKey) {
            return;
        }

        $returnedDeclare = self::$channel->queue_declare($bindingKey, false, true, false, false);
        self::$consumerCounts[$returnedDeclare[0] ?? ''] = $returnedDeclare[2] ?? 0;
        self::$currentBindingKey = $bindingKey;
    }
}
