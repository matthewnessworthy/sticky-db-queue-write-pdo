<?php

namespace App\Console\Commands;

use App\Jobs\ReadExpectRead;
use App\Jobs\ReadExpectWrite;
use App\Jobs\WriteExpectWrite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class GenerateReadsWithAWriteInChain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:read-write-read-chain
        {--count_repeats=1}
        {--count_reads=1}
        {--count_writes=1}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read, write, read';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $countRepeats = $this->option('count_repeats');
        $countReads = $this->option('count_reads');
        $countWrites = $this->option('count_writes');

        for ($j = 0; $j < $countRepeats; $j++) {
            $queue = [];

            for ($i = 0; $i < $countReads; $i++) {
                $queue[] = new ReadExpectRead();
            }
            for ($i = 0; $i < $countWrites; $i++) {
                $queue[] = new WriteExpectWrite();
            }
            for ($i = 0; $i < $countReads; $i++) {
                $queue[] = new ReadExpectWrite();
            }

            Bus::chain($queue)->dispatch();
        }

        return 0;
    }
}
