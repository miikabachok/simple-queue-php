### 1. Installation via [Composer](https://getcomposer.org/).

```
composer require miikabachok/simple-queue
```

### 2. Create queue worker and run it.

Create worker.php file. Make [Worker](https://github.com/miikabachok/simple-queue-php/blob/master/src/Worker.php) object, that require a queue object, that implement a [Queue](https://github.com/miikabachok/simple-queue-php/blob/master/src/Queues/Queue.php) interface, and a logger object, that implement [psr-3](https://www.php-fig.org/psr/psr-3/) logger interface.

```
<?php

declare(strict_types=1);

use MiiKabachok\SimpleQueue\Queues\Queue;
use MiiKabachok\SimpleQueue\Queues\RedisQueue;
use MiiKabachok\SimpleQueue\Worker;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

require __DIR__ . '/vendor/autoload.php';

// Create queue object.
$queue = (function (): Queue {
    $connection = new \Redis();

    $connection->connect('host');

    // If password is used.
    // $connection->auth('password');

    return new RedisQueue($connection);
})();

// Create logger object.
$logger = new class extends AbstractLogger
{
    private array $LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param Stringable|string $message
     * @param array $context
     * @throws JsonException
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        if (!\in_array($level, $this->LEVELS)) {
            throw new InvalidArgumentException(\sprintf('%s log level not found.', $level));
        }

        // Show log message in console.
        echo '[' . (new \DateTime())->format('d.m.Y H:i:s') . '] '
            . \strtoupper($level) . ': ' . $message
            . (!empty($context) ? (' ' . \json_encode($context, \JSON_THROW_ON_ERROR)) : '')
            . \PHP_EOL;
    }
};

// Create worker object.
$worker = new Worker($queue, $logger);

// Start worker.
$worker();
```

Run worker.

```
php worker.php
```

Now, worker is running, it's time to push some job to it.

**Important!** Worker must be restarted after any changes during development.

### 3. Create a job.

For example, inside your application, you need send some email notification. It is a good idea make job handle it. Any job must extend the [Job](https://github.com/miikabachok/simple-queue-php/blob/master/src/Jobs/Job.php) class, and implement the handle method.

```
class SendEmailNotificationJob extends Job
{
    /**
     * Send email.
     */
    protected function handle(): void
    {
        // Code here...
    }
}
```

### 4. Push job to the queue.

```
// Create queue object.
$queue = (function (): Queue {
    $connection = new \Redis();

    $connection->connect('host');

    // If password is used.
    // $connection->auth('password');

    return new RedisQueue($connection);
})();

// Push job to the queue.
$queue->push(new SendEmailNotificationJob());
```

Delayed job.

```
// Constructor usage example.
$job = new SendEmailNotificationJob(
    // One minute delay.
    (new \DateTime())->add(new \DateInterval('PT1M')),
);

// Or, that same operation can be made with method.
$job = new SendEmailNotificationJob();

// One minute delay.
$job->setExecuteAt((new \DateTime())->add(new \DateInterval('PT1M')));
```

Job with max number of failed attempts (how many times, job will try to be completed).

```
// When execution interval is not set, job will try another attempt immediately after failed attempt.

// Constructor usage example (without interval usage).
$job = new SendEmailNotificationJob(
    // One minute delay.
    (new \DateTime())->add(new \DateInterval('PT1M')),
    // Three attempts to complete the job.
    3,
);

// Or, that same operation can be made with method (without interval usage).
$job = new SendEmailNotificationJob();

// One minute delay.
$job->setExecuteAt((new \DateTime())->add(new \DateInterval('PT1M')));
// Three attempts to complete the job.
$job->setMaxNumberOfFailedAttempts(3);
```

Job with max number of failed attempts and execution interval after failure.

```
// When execution interval is set, job will try another attempts by interval.

// Constructor usage example (with interval usage).
$job = new SendEmailNotificationJob(
    // One minute delay.
    (new \DateTime())->add(new \DateInterval('PT1M')),
    // Three attempts to complete the job.
    3,
    // Try another attempt in three minutes after failed attempt.
    new \DateInterval('PT3M'),
);

// Or, that same operation can be made with method (with interval usage).
$job = new SendEmailNotificationJob();

// One minute delay.
$job->setExecuteAt((new \DateTime())->add(new \DateInterval('PT1M')));
// Three attempts to complete the job.
$job->setMaxNumberOfFailedAttempts(3);
// Try another attempt in three minutes after failed attempt.
$job->setExecuteAtIntervalAfterFailure(new \DateInterval('PT3M'));
```
