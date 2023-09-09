<?php

declare(strict_types=1);

namespace MiiKabachok\SimpleQueue\Queues;

use MiiKabachok\SimpleQueue\Jobs\Job;

class RedisQueue implements Queue
{
    private \Redis $connection;

    private const QUEUE_KEY = 'queue';
    private const DELAYED_QUEUE_KEY = self::QUEUE_KEY . ':delayed';

    /**
     * Create redis queue storage.
     *
     * @param \Redis $connection
     * @throws \Exception
     */
    public function __construct(\Redis $connection)
    {
        if (!$connection->isConnected()) {
            throw new \Exception('Live redis connection are required.');
        }

        $this->connection = $connection;
    }

    /**
     * Pop job from the redis queue storage.
     *
     * @return Job|null
     */
    public function pop(): ?Job
    {
        // Migrate delayed jobs, if they time is come.
        $this->migrateDelayedJobs();

        // Get job from the current queue.
        $job = $this->connection->lPop(self::QUEUE_KEY);

        return \is_string($job) ? \unserialize($job) : null;
    }

    /**
     * Push job into the redis queue storage.
     *
     * @param Job $job
     */
    public function push(Job $job): void
    {
        if ($job->isTimeToRun()) {
            // Job must be run immediately, so push the job into current queue.
            if (!$this->connection->rPush(self::QUEUE_KEY, \serialize($job))) {
                throw new \Exception('Record creation error.');
            }
        } else {
            // It is delayed job, so push it into delayed queue.
            if (
                !$this->connection->zAdd(
                    self::DELAYED_QUEUE_KEY,
                    (string) $job->getExecuteAt()->getTimestamp(),
                    \serialize($job)
                )
            ) {
                throw new \Exception('Record creation error.');
            }
        }
    }

    /**
     * If job inside delayed queue and is time to be executed,
     * than move this job from delayed queue, into the current queue.
     */
    private function migrateDelayedJobs(): void
    {
        $timestamp = (string) (new \DateTime())->getTimestamp();

        // Get delayed jobs, if they time to be executed is come.
        $jobs = $this->connection->zRangeByScore(self::DELAYED_QUEUE_KEY, '-inf', $timestamp);

        if (\count($jobs) > 0) {
            // Push jobs into the current queue.
            $this->connection->rPush(self::QUEUE_KEY, ...$jobs);

            // Remove jobs from the delayed queue.
            $this->connection->zRemRangeByScore(self::DELAYED_QUEUE_KEY, '-inf', $timestamp);
        }
    }
}
