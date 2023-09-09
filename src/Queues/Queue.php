<?php

declare(strict_types=1);

namespace MiiKabachok\SimpleQueue\Queues;

use MiiKabachok\SimpleQueue\Jobs\Job;

interface Queue
{
    /**
     * Pop job from the queue storage.
     *
     * @return Job|null
     */
    public function pop(): ?Job;

    /**
     * Push job into the queue storage.
     *
     * @param Job $job
     */
    public function push(Job $job): void;
}
