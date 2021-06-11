<?php

namespace App\Console\Commands;

use App\Jobs\ReadExpectRead;
use Illuminate\Console\Command;

class GenerateReads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:read
        {--count_reads=1}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = $this->option('count_reads');
        for ($i = 0; $i < $count; $i++) {
            dispatch(new ReadExpectRead());
        }

        return 0;
    }
}
