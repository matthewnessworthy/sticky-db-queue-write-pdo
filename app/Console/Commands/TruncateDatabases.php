<?php

namespace App\Console\Commands;

use App\Jobs\WriteExpectWrite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:truncate-databases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate databases';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::connection('mysql')->unprepared(DB::raw('TRUNCATE examples'));
        DB::connection('mysql_read')->unprepared(DB::raw('TRUNCATE examples'));

        return 0;
    }
}
