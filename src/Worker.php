<?php

declare(strict_types=1);

namespace MiiKabachok\SimpleQueue;

use MiiKabachok\SimpleQueue\Queues\Queue;
use Psr\Log\LoggerInterface;

class Worker
{
    private Queue $queue;
    private LoggerInterface $logger;

    /**
     * Create queue worker.
     *
     * @param Queue $queue
     * @param LoggerInterface $logger
     */
    public function __construct(Queue $queue, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->logger = $logger;
    }

    /**
     * Run queue worker.
     */
    public function __invoke()
    {
        try {
            // Infinite loop.
            for (;;) {
                try {
                    // Get job from the queue.
                    $job = $this->queue->pop();

                    if (\is_null($job)) {
                        // If job is not found, than wait for it.
                        \sleep(3);

                        continue;
                    }

                    if (!$job->isTimeToRun()) {
                        // If job was found, but by some reasons is not the time to be executed,
                        // than push the job back into the queue.
                        $this->queue->push($job);

                        continue;
                    }

                    try {
                        // Run job itself.
                        $job->run();

                        // Log info about successful job execution.
                        $this->logger->info(
                            \sprintf(
                                '%s job was successfully completed. Number of failed attempts: %d.',
                                \get_class($job),
                                $job->getNumberOfFailedAttempts()
                            )
                        );
                    } catch (\Throwable $e) {
                        if (!$job->isCompleted() && !$job->isFailed()) {
                            // If job is not completed and not failed (there is are available execution attempts),
                            // than back the job into the queue.
                            $this->queue->push($job);
                        }

                        // Log info about job execution error.
                        $this->logger->error($e->getMessage(), $e->getTrace());
                    }
                } catch (\Throwable $e) {
                    // Log info about some critical error.
                    $this->logger->critical($e->getMessage(), $e->getTrace());
                }
            }
        } catch (\Throwable $e) {
            // Log info about some critical error.
            $this->logger->critical($e->getMessage(), $e->getTrace());
        }
    }
}
