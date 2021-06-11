<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class WriteExpectWrite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::table('examples')
            ->insert(['content' => rand(1, 1000)]);

        $hasRows = DB::table('examples')->count();

        if ($hasRows) {
            dump('SUCCESS!!! Write PDO after write action');
        } else {
            dump('FAILURE!!! Read PDO after write action');
        }
    }
}
