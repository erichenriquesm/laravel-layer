<?php

namespace App\Console\Commands;

use Domain\Shared\Helpers\Queue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class Work extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'work {queue} {--prefetch=} {--assure=false} {--onerror=requeue} {--delay=0} {--maxWorkCount=0} {--requeueMode=} {--requeueTime=5} {--processesEachItem=} {--processEveryTime=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando responsável por consumir uma fila específica.';

    private $workCount = 0;

    private $processesEachItemCount = 0;

    /**
     * Calcula o n-ésimo número de Fibonacci usando a fórmula de Binet.
     *
     * A fórmula de Binet é uma forma matemática para calcular rapidamente o número de Fibonacci
     * sem precisar calcular os números anteriores, baseada na razão áurea (phi).
     * A fórmula é dada por: F(n) = ((phi ^ n) - (1 - phi) ^ n) / sqrt(5), onde phi é a razão áurea.
     *
     * @param int $number O índice do número de Fibonacci que queremos calcular. Deve ser um número inteiro não negativo.
     *
     * @return int O n-ésimo número de Fibonacci, arredondado para o inteiro mais próximo.
     */
    private function getFib($number)
    {
        return round(pow((sqrt(5) + 1) / 2, $number) / sqrt(5));
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->argument('queue'); # Recupera o argumento 'queue', que contém o nome da fila a ser processada
        $prefetch = (int) $this->option('prefetch'); # Converte a opção 'prefetch' para inteiro, indicando o número de mensagens que o consumidor deve processar simultaneamente
        $assure = ($this->option('assure') === 'true'); # Converte a opção 'assure' para booleano, indicando se a operação deve ser garantida (true) ou não (false)
        $onerror = $this->option('onerror'); # Recupera a opção 'onerror', que define a ação a ser tomada em caso de erro (como 'retries' ou 'stop')
        $delay = $this->option('delay') ?? 0; # Recupera a opção 'delay' (tempo de atraso) ou define como 0 caso não esteja presente
        $requeueMode = $this->option('requeueMode') ?? 'nack'; # Recupera a opção 'requeueMode', que define o modo de reencaminhamento da mensagem (padrão é 'nack')
        $maxWorkCount = (int) $this->option('maxWorkCount') ?? 0; # Converte a opção 'maxWorkCount' para inteiro, que define o número máximo de itens a serem processados
        $requeueTime = (int) $this->option('requeueTime') ?? 60; # Converte a opção 'requeueTime' para inteiro, que define o tempo de espera para reencaminhar a mensagem (padrão é 60 segundos)
        $processesEachItem = $this->option('processesEachItem') ?? 0; # Recupera a opção 'processesEachItem', que define o número de itens a serem processados por vez
        $processEveryTime = $this->option('processEveryTime') ?? 0; # Recupera a opção 'processEveryTime', que define a frequência de execução do processamento

        # Se exisitr prefetch, será configurado o QoS do consumidor.
        if ($prefetch) {
            Queue::setQos(0, $prefetch, false);
        }

        # Obtém a quantidade de consumidores da fila a ser consumida.
        $consumerCount = Queue::getConsumerCount($queue);
        
        # Se existir delay, o delay será ajustado adicionando um número de Fibonacci relacionado ao número de consumidores.
        if ($delay > 0 && $consumerCount > 0) {
            $delay = $delay + $this->getFib($consumerCount + 2);
        }

        Queue::consume($queue, function (AMQPMessage $message) use ($queue, $assure, $onerror, $delay, $maxWorkCount, $requeueMode, $requeueTime, $processesEachItem, $processEveryTime) {
            # Se exisitr um limite de consumidores.
            if ($maxWorkCount) {
                $this->workCount++;
                # Se a quantidade de consumidores for maior que o máximo permitido, será cancelado o consumidor.
                if ($this->workCount > $maxWorkCount) {
                    exit(0);
                }
            }

            try {
                $res = Queue::processMessage($message, $delay);
                if ($delay && is_string($res) && $res = '__delay__') {
                    sleep($requeueTime);
                    if ($requeueMode === 'requeue') {
                        Queue::directPublish($queue, $message);
                        $message->ack();
                    } else {
                        $message->nack(true);
                    }
                } elseif ($assure) {
                    if ($res) {
                        $message->ack();
                    } else {
                        Log::error('Worker -> assure -> requeuing', [
                            'queue' => $message->getRoutingKey(),
                            'message' => $message->getBody(),
                            'res' => $res,
                        ]);
                        sleep($requeueTime);
                        if ($requeueMode === 'requeue') {
                            Queue::directPublish($queue, $message);
                            $message->ack();
                        } else {
                            $message->nack(true);
                        }
                    }
                } elseif ($processesEachItem) {

                    $this->processesEachItemCount++;

                    Log::debug('PROCESSANDO POR PACOTE DE ITEM', [
                        'requeueMode' => $requeueMode,
                        'processesEachItemCount' => $this->processesEachItemCount ,
                        'processesEachItem' => (int)$processesEachItem
                    ]);

                    if ($this->processesEachItemCount === (int) $processesEachItem) {
                        $this->processesEachItemCount = 0;
                        sleep((int)$processEveryTime);
                    }

                    $message->ack();

                } else {
                    $message->ack();
                }

                Queue::commit();
            } catch (\Throwable $th) {
                if ($onerror === 'requeue') {
                    Log::error('Worker -> error -> requeuing', [
                        'queue'      => $message->getRoutingKey(),
                        'errCode'    => $th->getCode(),
                        'errMessage' => $th->getMessage(),
                        'trace'      => $th->getTrace(),
                    ]);
                    sleep($requeueTime);
                    if ($requeueMode === 'requeue') {
                        Queue::directPublish($queue, $message);
                        $message->ack();
                    } else {
                        $message->nack(true);
                    }
                } else {
                    Log::error('Worker -> error -> not requeuing', [
                        'queue' => $message->getRoutingKey(),
                        'message' => $message->getBody(),
                        'errCode' => $th->getCode(),
                        'errMessage' => $th->getMessage(),
                        'trace' => $th->getTrace(),
                    ]);
                    $message->ack();
                }
                Queue::commit();
            }
        });
    }
}
