<?php

declare(strict_types=1);

namespace MiiKabachok\SimpleQueue\Jobs;

abstract class Job
{
    // Status of the current job.
    private bool $isCompleted;

    // Counter of failed job attempts.
    private int $numberOfFailedAttempts;

    // Max number of failed attempts (how many times, job will try to be completed).
    private ?int $maxNumberOfFailedAttempts;

    // When job was created.
    private \DateTime $createdAt;

    // When job must be executed (if job is delayed).
    private ?\DateTime $executeAt;

    // Execution interval, after job execution error.
    private ?\DateInterval $executeAtIntervalAfterFailure;

    /**
     * Create the job.
     *
     * @param \DateTime|null $executeAt
     * @param int|null $maxNumberOfFailedAttempts
     * @param \DateInterval|null $executeAtIntervalAfterFailure
     */
    public function __construct(
        ?\DateTime $executeAt = null,
        ?int $maxNumberOfFailedAttempts = null,
        ?\DateInterval $executeAtIntervalAfterFailure = null,
    )
    {
        // The job, after creation, of course is not completed.
        $this->isCompleted = false;

        $this->maxNumberOfFailedAttempts = $maxNumberOfFailedAttempts;

        // The job, after creation, has no any failed attempts.
        $this->numberOfFailedAttempts = 0;

        // Create job creation timestamp.
        $this->createdAt = new \DateTime();

        $this->executeAt = $executeAt;

        $this->executeAtIntervalAfterFailure = $executeAtIntervalAfterFailure;
    }

    /**
     * Job serialization.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'is_completed' => $this->isCompleted,
            'max_number_of_failed_attempts' => $this->maxNumberOfFailedAttempts,
            'number_of_failed_attempts' => $this->numberOfFailedAttempts,
            'created_at' => \serialize($this->createdAt),
            'execute_at' => !\is_null($this->executeAt)
                ? \serialize($this->executeAt)
                : null,
            'execute_at_interval_after_failure' => !\is_null($this->executeAtIntervalAfterFailure)
                ? \serialize($this->executeAtIntervalAfterFailure)
                : null,
        ];
    }

    /**
     * Job deserialization.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->isCompleted = $data['is_completed'];
        $this->maxNumberOfFailedAttempts = $data['max_number_of_failed_attempts'];
        $this->numberOfFailedAttempts = $data['number_of_failed_attempts'];
        $this->createdAt = \unserialize($data['created_at'], ['allowed_classes' => [\DateTime::class]]);
        $this->executeAt = !\is_null($data['execute_at'])
            ? \unserialize($data['execute_at'], ['allowed_classes' => [\DateTime::class]])
            : null;
        $this->executeAtIntervalAfterFailure = !\is_null($data['execute_at_interval_after_failure'])
            ? \unserialize($data['execute_at_interval_after_failure'], ['allowed_classes' => [\DateInterval::class]])
            : null;
    }

    /**
     * Job itself. Must throw exception on failure.
     */
    abstract protected function handle(): void;

    /**
     * Run the job.
     *
     * @throws \Exception
     */
    public function run(): void
    {
        if (!$this->isTimeToRun()) {
            // Is not the time for job to be executed, so push the job back into the queue.
            throw new \Exception(
                \sprintf('%s job must be executed no earlier than the specified time.', static::class)
            );
        }

        if ($this->isCompleted()) {
            // Is nothing to execute, job is already executed and successfully completed.
            throw new \Exception(\sprintf('%s job is completed already.', static::class));
        }

        if ($this->isFailed()) {
            // Job is failed, and can't be executed, because there is no available execution attempts.
            throw new \Exception(\sprintf('%s job is failed.', static::class));
        }

        try {
            // Execute the job itself.
            $this->handle();

            // Set job as complete.
            $this->setCompleted();
        } catch (\Exception $e) {
            // If job throw some exception, then increment job failed attempt.
            $this->setFailedAttempt();

            throw $e;
        }
    }

    /**
     * Get job completion flag.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    /**
     * Check if job is time to be executed.
     *
     * @return bool
     */
    public function isTimeToRun(): bool
    {
        return \is_null($this->executeAt) || (new \DateTime())->getTimestamp() >= $this->executeAt->getTimestamp();
    }

    /**
     * Check if job is failed (execution counter has been reached of max failed attempts).
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return !$this->isCompleted && $this->isNumberOfFailedAttemptsHasBeenReached();
    }

    /**
     * Get number of failed job executions.
     *
     * @return int
     */
    public function getNumberOfFailedAttempts(): int
    {
        return $this->numberOfFailedAttempts;
    }

    /**
     * Get job creation timestamp.
     *
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get job delayed execution timestamp (when job must be executed).
     *
     * @return \DateTime|null
     */
    public function getExecuteAt(): ?\DateTime
    {
        return $this->executeAt;
    }

    /**
     * Set job delayed execution timestamp (when job must be executed).
     *
     * @param \DateTime|null $executeAt
     */
    public function setExecuteAt(?\DateTime $executeAt = null): void
    {
        $this->executeAt = $executeAt;
    }

    /**
     * Set the interval at which job will be try execute, after failed attempt.
     *
     * @param \DateInterval|null $executeAtIntervalAfterFailure
     */
    public function setExecuteAtIntervalAfterFailure(?\DateInterval $executeAtIntervalAfterFailure = null): void
    {
        $this->executeAtIntervalAfterFailure = $executeAtIntervalAfterFailure;
    }

    /**
     * Set max number of failed attempts (how many times, job will try to be executed).
     *
     * @param int|null $maxNumberOfFailedAttempts
     */
    public function setMaxNumberOfFailedAttempts(?int $maxNumberOfFailedAttempts = null): void
    {
        $this->maxNumberOfFailedAttempts = $maxNumberOfFailedAttempts;
    }

    /**
     * Check if execution counter has been reached of max failed attempts.
     *
     * @return bool
     */
    private function isNumberOfFailedAttemptsHasBeenReached(): bool
    {
        return !\is_null($this->maxNumberOfFailedAttempts)
            && $this->maxNumberOfFailedAttempts <= $this->numberOfFailedAttempts;
    }

    /**
     * Set job as completed.
     */
    private function setCompleted(): void
    {
        $this->isCompleted = true;
    }

    /**
     * Increment job failed attempt.
     */
    private function setFailedAttempt(): void
    {
        ++$this->numberOfFailedAttempts;

        if (!\is_null($this->executeAtIntervalAfterFailure) && !$this->isNumberOfFailedAttemptsHasBeenReached()) {
            $this->setExecuteAt((new \DateTime())->add($this->executeAtIntervalAfterFailure));
        }
    }
}
