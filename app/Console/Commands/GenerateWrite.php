<?php

namespace App\Console\Commands;

use App\Jobs\WriteExpectWrite;
use Illuminate\Console\Command;

class GenerateWrite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:write
        {--count_writes=1}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $countWrites = $this->option('count_writes');

        for ($i = 0; $i < $countWrites; $i++) {
            dispatch(new WriteExpectWrite());
        }

        return 0;
    }
}
