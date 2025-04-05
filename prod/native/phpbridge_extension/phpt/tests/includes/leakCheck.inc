<?php
declare(strict_types=1);

function leakCheck(callable $fn): void
{
    gc_collect_cycles();

    $before = 0;
    $after = 0;

    $before = memory_get_usage();
    $fn();
    gc_collect_cycles();
    $after = memory_get_usage();



    if ($before !== $after) {
        echo "Memory diff: " . ($after - $before) . " bytes\n";
    }

}