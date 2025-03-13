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
     * Método responsável por publicar uma mensagem em uma fila específica.
     *
     * @param string $queue Chave de ligação (nome da fila).
     *
     * @param string $class Classe que terá o método a ser executado.
     *
     * @param string $method Nome do método a ser executado.
     * 
     * @param ...$args Argumentos que serão passados ao método.
     *
     * @return void
     */
    public static function publish(string $queue, string $class, string $method, ...$args) : void
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
        self::directPublish($queue, $message);
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
     * Publica a mensagem no fila específico.
     *
     * @param string $queue Chave de ligação (nome da fila).
     *
     * @param AMQPMessage $message mensagem a ser enviada.
     *
     * @return void.
     */
    public static function directPublish(string $queue, AMQPMessage $message) : void
    {
        # Inicializa a conexão com o RabbitMQ.
        self::boot();
        # Declara a fila a ser passado a mensagem.
        self::declareQueue($queue);
        # Publica a mensagem na fila.
        self::$channel->basic_publish($message, '', $queue);
        self::$dirty = true;
        self::$dirtyCount++;
    }

    /**
     * Declara a fila a ser passado qualquer mensagem.
     *
     * @param string $queue Chave de ligação (nome da fila).
     *
     * @return void
     */
    private static function declareQueue(string $queue): void
    {
        # Se a fila atual for igual a passada por parâmetro simplesmente retorna.
        if (self::$currentqueue === $queue) {
            return;
        }

        # Declara a queue
        $returnedDeclare = self::$channel->queue_declare(
            $queue,  # Nome da fila
            false,   # Parâmetro "passive" - não falha se a fila não existir
            true,    # Parâmetro "durable" - a fila é persistente e sobrevive a reinicializações do RabbitMQ
            false,   # Parâmetro "exclusive" - define se a fila é exclusiva para este consumidor
            false    # Parâmetro "auto_delete" - define se a fila será excluída automaticamente quando não houver consumidores
        );
        

        self::$consumerCounts[$returnedDeclare[0] ?? ''] = $returnedDeclare[2] ?? 0;
        # Define a fila atual.
        self::$currentqueue = $queue;
    }

    /**
     * Define o QoS(Quality of Service) do consumidor.
     *
     * @param int $prefetchSize Tamanho do limite em bytes da mensagem a ser configurado.
     *
     * @param int $prefetchCount Quantidade de menssagens a serem  processadas de uma vez.
     *
     * @param bool $aGlobal  Define se a configuração do QoS irá ser global ou não.
     */
    public static function setQos(int $prefetchSize, int $prefetchCount, bool $aGlobal)
    {
        # Inicializa a conexão com o RabbitMQ.
        self::boot();

        # Define a qualidade de serviço(QoS).
        self::$channel->basic_qos(
            $prefetchSize, # Tamanho de mensagem permitido (se for zero, significa que não possui limite).
            $prefetchCount, # Quantidade de mensagens que podem ser enviadas ao consumidor antes de ser assinado o processo 
            $aGlobal # Define se a configuração do QoS irá ser global ou não.
        );
    }

    /**
     * Obtém o número de consumidores associados a uma fila.
     *
     * @param string $queue Fila que irá ser verificado o número de consumidores.
     *
     * @return int O número de consumidores associados à fila.
     */
    public static function getConsumerCount(string $queue): int
    {
        # Inicializa a conexão com o RabbitMQ.
        self::boot();

        # Chama o método 'declareQueue' para garantir que a fila esteja configurada corretamente
        self::declareQueue($queue);

        # Retorna o número de consumidores associados à fila.
        return self::$consumerCounts[$queue] ?? 0;
    }

    /**
     * Comita um lote de mensagens publicadas caso haja alterações.
     * 
     * Este método verifica se o lote de mensagens (representado por `self::$dirty`)
     * precisa ser enviado. Caso haja mensagens não enviadas, ele chama o método `publish_batch` 
     * para publicar todas as mensagens de uma vez, reseta o estado de alterações e 
     * redefine a contagem de mensagens não enviadas.
     * 
     * @return void
     */
    public static function commit()
    {
        # Inicializa a conexão com o RabbitMQ.
        self::boot();
        
        if (self::$dirty) { # Verifica se há mensagens não enviadas (lote sujo).
            self::$channel->publish_batch(); # Publica todas as mensagens acumuladas no lote.
            self::$dirty = false; # Reseta o estado de sujo, indicando que o lote foi enviado.
            self::$dirtyCount = 0; # Reseta a contagem de mensagens não enviadas.
        }
    }


    /**
     * Processa a mensagem vinda do consumidor.
     *
     * @param AMQPMessage $message mensagem a ser processada.
     *
     * @param int $delay Delay em segundos.
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
                __CLASS__.'.'.__FUNCTION__.": \"{$re->getMessage()}\"",
                [
                    'messageBody' => $messageBody,
                    'class' => $class,
                    'function' => $method,
                ]
            );

            return false;
        }

        if ($reflection->isStatic()) {
            return $reflection->invoke(null, ...$args);
        }

        return $reflection->invoke(new $class, ...$args);
    }

    /**
     * Inicia o consumo de mensagens de uma fila RabbitMQ e processa cada mensagem com uma função de callback.
     *
     * Este método configura um consumidor para a fila especificada e define a função de callback que será chamada
     * cada vez que uma nova mensagem for recebida da fila. A função de callback recebe a mensagem como argumento.
     *
     * @param string $queue O nome da fila a ser consumida. Este parâmetro define de qual fila as mensagens serão retiradas.
     * 
     * @param Closure $closure A função de callback que será chamada para processar cada mensagem recebida da fila.
     *                         A função recebe a mensagem como argumento para processamento.
     *
     * @return void
     */
    public static function consume(string $queue, Closure $closure) : void
    {
        # Inicializa a conexão com o RabbitMQ.
        self::boot();
        # Chama o método 'declareQueue' para garantir que a fila esteja configurada corretamente
        self::declareQueue($queue);

        # Realiza o consumo das mensagens dentro da fila.
        self::$channel->basic_consume(
            $queue, # O nome da fila a ser consumida. A fila de onde as mensagens serão retiradas.
            '',     # Consumer tag (identificador único para o consumidor). Se vazio, o RabbitMQ cria um identificador único automaticamente.
            false,  # Se o consumidor deve receber confirmação automática (acknowledgement) das mensagens. Quando 'false', a confirmação não será automática.
            false,  # Se o consumidor deve ser exclusivo, ou seja, se a fila só pode ser consumida por esse único consumidor. 'false' significa que outros consumidores podem consumir da mesma fila.
            false,  # Se a fila deve ser excluída automaticamente quando não houver consumidores. 'false' significa que a fila não será excluída.
            false,  # Se o consumidor deve ser em modo **não bloqueante** (não aguardar indefinidamente por novas mensagens). 'false' significa que o consumidor pode ser bloqueado e esperar por mensagens.
            function ($message) use ($closure) {   # Função de callback que será executada quando uma mensagem for recebida.
                $closure($message); # Chama a função de fechamento (closure) passada para processar a mensagem.
            }
        );

        # Enquanto os canal estiver consumindo, o canal deve esperar de terminar o processamento.
        while (self::$channel->is_consuming()) {
            self::$channel->wait();
        }
    }

    public static function testConsume($data)
    {
        Log::info('TESTING_QUEUE_CONSUMO', $data);
    }
}
