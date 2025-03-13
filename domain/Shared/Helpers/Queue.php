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

    /**
     * Inicia a conexão com o RabbitMQ e abre o canal.
     *
     * @return void 
     */
    private static function boot()
    {
        # Se já estiver inicializado, não é refeito a conexão.
        if (self::$isBooted) {
            return false;
        }

        try {
            # Tenta realizar a conexão com o RabbitMQ.
            self::connect();
        } catch (\Throwable $th) {
            Log::error('Queue -> failed to connect', ['code' => $th->getCode(), 'message' => $th->getMessage()]);
        }
        try {
            # Obtém o canal para que seja possível realizar a inserção e consumo de mensagens.
            self::getChannel();
        } catch (\Throwable $th) {
            Log::error('Queue -> failed to get channel', ['code' => $th->getCode(), 'message' => $th->getMessage()]);
        }

        # Define que já foi inicializado.
        self::$isBooted = true;

        return true;
    }

    /**
     * Método responsável por publicar uma mensagem em uma binding key especifica.
     *
     * @param string $bindingKey Chave de ligação (nome da fila).
     *
     * @param string $class Classe que terá o método a ser executado.
     *
     * @param string $method Nome do método a ser executado.
     * 
     * @param ...$args Argumentos que serão passados ao método.
     *
     * @return void
     */
    public static function publish(string $bindingKey, string $class, string $method, ...$args) : void
    {   
        /** 
         * Define a mensagem a ser enviado pelo RabbitMQ passanddo primeiramente  por um tratamento já que o RabbitMQ
         * espera receber a mensagem no formato AMQPMessage
        */
        $message = self::parseMessage([
            '__RID' => Hash::make(Str::random(10)),
            '__class' => $class,
            '__method' => $method,
            '__args' => $args,
            '__publishedAt' => Carbon::now(),
            '__messageID' => Str::uuid(),
        ]);

        # Publica a mensagem.
        self::directPublish($bindingKey, $message);
    }
    
    /**
     * Atribui a conexão à propriedade connection com o valor sendo uma instãncia AMQPStreamConnection.
     *
     * @return void
     */
    private static function connect() : void
    {
        self::$connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),  # Host do RabbitMQ (endereço do servidor)
            env('RABBITMQ_PORT'),  # Porta do RabbitMQ
            env('RABBITMQ_USER'),  # Usuário para autenticação
            env('RABBITMQ_PASSWORD')  # Senha para autenticação
        );
        
    }
    
    /**
     * Atribui a propriedade channel com o canal obtido através da conexão.
     *
     * @return void
     */
    private static function getChannel(): void
    {
        self::$channel = self::$connection->channel();
    }
    
    /**
     * Recebe a mensagem a ser enviada para o RabbitMQ e retorna no formato AMQPMessage.
     *
     * @param array $message Mensagem a ser enviada.
     *
     * @return AMQPMessage Mensagem no formarto AMQP.
     */
    private static function parseMessage(array $message): AMQPMessage
    {
        /**
         * 1. Retorna uma instância da classe AMQPMessage;
         * 2. Primeiro parâmetro é a mensagem que será feito um serialize, pois a mensagem é preciso ser uma string;
         * 3. Segundo parâmetro são as configurações da mensagem em que é explicado que o tipo do conteúdo é uma serialização do php
         * e o modo de entrega é o 2(mantém a mensagem no armazenamento do RabbitMQ caso seja reinicializado).
         */
        return new AMQPMessage(serialize($message), [
            'content-type' => 'application/php-serialized',
            'delivery_mode' => 2,
        ]);
    }
    
    /**
     * Publica a mensagem no binding key específico.
     *
     * @param string $bindingKey Chave de ligação (nome da fila).
     *
     * @param AMQPMessage $message mensagem a ser enviada.
     *
     * @return void.
     */
    private static function directPublish(string $bindingKey, AMQPMessage $message) : void
    {
        # Inicializa a conexão com o RabbitMQ.
        self::boot();
        # Declara a binding key a ser passado a mensagem.
        self::declareBindingKey($bindingKey);
        # Publica a mensagem na binding key.
        self::$channel->basic_publish($message, '', $bindingKey);
        self::$dirty = true;
        self::$dirtyCount++;
    }

    /**
     * Declara a binding key a ser passado qualquer mensagem.
     *
     * @param string $bindingKey Chave de ligação (nome da fila).
     *
     * @return void
     */
    private static function declareBindingKey(string $bindingKey): void
    {
        # Se a binding key atual for igual a passada por parâmetro simplesmente retorna.
        if (self::$currentBindingKey === $bindingKey) {
            return;
        }

        # Declara a bindingKey
        $returnedDeclare = self::$channel->queue_declare(
            $bindingKey, # Nome da fila
            false,       # Parâmetro que verifica a existência d fila. 
            true         # Faz a fila persistir caso o RabbitMQ reinicie.
        );

        self::$consumerCounts[$returnedDeclare[0] ?? ''] = $returnedDeclare[2] ?? 0;

        # Define a binding key atual.
        self::$currentBindingKey = $bindingKey;
    }
}
