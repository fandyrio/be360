<?php

use App\Models\Tref_users;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\Tref_zonasi;
use App\Models\Zonasi_satker;
use App\Services\zonasiSatkerService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function(Schedule $schedule){
        $schedule->call(function(){
            //schedule untuk zonasi yang end_date nya lebih kecil dari hari ini (Menungug tanggal mulai)
            $effected=Tref_zonasi::where('end_date', '>=', today())
                        ->where('start_date', '<=', today())
                        ->where('proses_id', 4)
                        ->pluck('IdZona')->toArray();
            //Otomatisasi selesai setelah tanggal 
            $effected_ids=Tref_zonasi::where('end_date', '<', today())
                                    ->where('proses_id', '<=', 5)
                                    ->pluck('IdZona')->toArray();
            if(count($effected) > 0){
                Tref_zonasi::whereIn('IdZona', $effected)->update(['proses_id' => 5]);
                
                $get_zonasi_satker=Zonasi_satker::whereIn('IdZona', $effected)->get();
                foreach($get_zonasi_satker as $list_data){
                    Cache::store('redis')->forget("zonasi_satker1_{$list_data['IdZonaSatker']}");
                }
                sendWaObserveeHelpers();
            }
            if(count($effected_ids) > 0){
                Tref_zonasi::whereIn('IdZona', $effected_ids)->update(['proses_id'=>6]);
                
                $get_zonasi_satker=Zonasi_satker::whereIn('IdZona', $effected_ids)->get();
                foreach($get_zonasi_satker as $list_data){
                    Cache::store('redis')->forget("zonasi_satker1_{$list_data['IdZonaSatker']}");
                }
            }
        })->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withProviders([
        App\Providers\RouteServiceProvider::class, // << tambahkan ini
    ])->create();
