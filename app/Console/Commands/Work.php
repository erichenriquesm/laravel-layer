<?php

namespace App\Console\Commands;

use Domain\Shared\Helpers\Queue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
     * Nth Fibonacci number via Binet's closed form, so no iteration is needed.
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
        $queue = $this->argument('queue');
        $prefetch = (int) $this->option('prefetch');
        $assure = ($this->option('assure') === 'true');
        $onerror = $this->option('onerror');
        $delay = $this->option('delay') ?? 0;
        $requeueMode = $this->option('requeueMode') ?? 'nack';
        $maxWorkCount = (int) $this->option('maxWorkCount') ?? 0;
        $requeueTime = (int) $this->option('requeueTime') ?? 60;
        $processesEachItem = $this->option('processesEachItem') ?? 0;
        $processEveryTime = $this->option('processEveryTime') ?? 0;

        if ($prefetch) {
            Queue::setQos(0, $prefetch, false);
        }

        $consumerCount = Queue::getConsumerCount($queue);

        # Spread the delay across concurrent consumers so they do not wake up in lockstep.
        if ($delay > 0 && $consumerCount > 0) {
            $delay = $delay + $this->getFib($consumerCount + 2);
        }

        Queue::consume($queue, function (AMQPMessage $message) use ($queue, $assure, $onerror, $delay, $maxWorkCount, $requeueMode, $requeueTime, $processesEachItem, $processEveryTime) {
            if ($maxWorkCount) {
                $this->workCount++;
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

                    Log::debug('Worker -> processing item batch', [
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
