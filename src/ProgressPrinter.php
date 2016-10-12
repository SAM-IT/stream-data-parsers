<?php


namespace SamIT\Streams;


class ProgressPrinter
{
    /**
     * @var \Closure
     */
    private $logCallback;

    public function __construct(\Closure $logCallback)
    {
        $this->logCallback = $logCallback;
    }


    private $progress = [];

    /**
     * Print progress information
     * @param float $progress The progress mapped to (0..1)
     * @param string $name The name of the indicator.
     */
    public function progress($progress = 0, $name = 'progress') {
        if ($progress === 0) {
            $this->progress[$name] = microtime(true);
            $remaining = "unknown";
            $spent = "0s";
        } else {
            $time = microtime(true) - $this->progress[$name];
            $remaining = number_format(($time / $progress) * (1 - $progress), 1) ."s";
            $spent = number_format($time, 1) ."s";
        }
        $mem = number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . "M";

        call_user_func($this->logCallback, "Progress $name: " . number_format($progress * 100, 1) . "%, time spent: $spent, remaining time: $remaining, mem: $mem\n");
    }

    public function __invoke($progress = 0, $name = 'progress')
    {
        $this->progress($progress, $name);
    }



}