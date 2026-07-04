<?php

declare(strict_types=1);

namespace app\enums;

enum SyncJobStatusEnum: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case DONE       = 'done';
    case FAILED     = 'failed';
    // Terminal, deliberate non-processing: a permanent condition (e.g. product not in the affiliate
    // program) that will never succeed, so the worker parks it here instead of retrying to `failed`.
    case SKIPPED    = 'skipped';
}
