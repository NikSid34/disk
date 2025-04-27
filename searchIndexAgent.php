<?php

declare(strict_types=1);

use app\container\AppContainer;

require __DIR__ . '/vendor/autoload.php';

// Скрипт для отложенной поисковой индексации файлов на cron
$container = AppContainer::fromDefaultConfig();
$batchSize = (int) (getenv('SIMPLEDISK_SEARCH_INDEX_AGENT_BATCH') ?: 25);

$summary = $container->fileIndexingService()->processPendingFiles($batchSize);

echo \sprintf(
        "claimed=%d indexed=%d failed=%d requeued=%d\n",
        $summary['claimed'],
        $summary['indexed'],
        $summary['failed'],
        $summary['requeued']
);

exit($summary['failed'] > 0 ? 1 : 0);
