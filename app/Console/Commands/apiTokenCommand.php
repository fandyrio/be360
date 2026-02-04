<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class apiTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:api-token-command {env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $token=Str::random(60);
        $envi=$this->argument('env');
        DB::table('api_token')->insert([
            'token'=>hash('sha256', $token),
            'env'=>$envi
        ]);
        $this->info($token);
        return 0;
    }
}
