<?php

namespace App\Console\Commands;

use App\Jobs\ReadExpectRead;
use App\Jobs\WriteExpectWrite;
use Illuminate\Console\Command;

class GenerateReadsWithAWrite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:read-write-read
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
        $countReads = $this->option('count_reads');
        $countWrites = $this->option('count_writes');

        for ($i = 0; $i < $countReads; $i++) {
            dispatch(new ReadExpectRead());
        }
        for ($i = 0; $i < $countWrites; $i++) {
            dispatch(new WriteExpectWrite());
        }
        for ($i = 0; $i < $countReads; $i++) {
            dispatch(new ReadExpectRead());
        }

        return 0;
    }
}
