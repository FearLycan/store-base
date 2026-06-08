<?php

declare(strict_types=1);

namespace app\enums;

enum SyncJobStatusEnum: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case DONE       = 'done';
    case FAILED     = 'failed';
}
