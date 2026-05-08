<?php

namespace App\Listeners;

use App\Events\PesertaInsertedEvent;
use App\Models\Log_msg;
use App\Models\Trans_jabatan_kosong;
use App\Models\Tref_zonasi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Zonasi_satker;
use App\Services\zonasiService;
use Illuminate\Support\Facades\DB;

class PesertaInsertedListener
{
    /**
     * Create the event listener.
     */
    protected $zonasiService;
    public function __construct(zonasiService $zonasi_service)
    {
        //
        $this->zonasiService=$zonasi_service;
    }

    /**
     * Handle the event.
     */
    public function handle(PesertaInsertedEvent $event): void
    {
        //
        $id_zonasi=$event->id_zonasi;
        // $total_batch=$event->total_batch;
        $get_total_batch=Log_msg::where('data_id', $id_zonasi)
                            ->where('category', 'jobs_peserta')
                            ->where('status', 'prepare')
                            ->where('activity', 'current')
                            ->first();
        $explode_msg_batch=explode(" ", $get_total_batch['msg']);
        $total_batch=(int)$explode_msg_batch[1];
        $category="jobs_peserta";
        $jumlah_job=Log_msg::where('data_id', $id_zonasi)->where('category', $category)->where('status', 'progress')->count();
        // $get_data=Zonasi_satker::where('id');
        $jumlah_job+=1;
        $msg=$jumlah_job." dari ".$total_batch." Jobs Selesai";
        $this->zonasiService->saveLog($id_zonasi, $category, $msg, "progress");

        if((int)$jumlah_job === (int)$total_batch){
            $category="jobs_peserta";
            $msg="Jobs Insert Peserta dengan total : ".$total_batch." Jobs Selesai";
            try{
                DB::beginTransaction();
                    $this->zonasiService->saveLog($id_zonasi, $category, $msg, "finished");
                    $get_kosong=Trans_jabatan_kosong::where('id_zonasi', $id_zonasi)->count();
                    $proses_id=4;
                    if($get_kosong > 0){
                        $send_wa=$this->zonasiService->sendNotifJabatanKosong($id_zonasi);
                        $proses_id=3;
                    }
                   $this->zonasiService->updateProsesZonasi($id_zonasi, $proses_id);
                DB::commit();
            }catch(\Exception $e){
                DB::rollBack();
                $msg=$e->getMessage();
                $this->zonasiService->saveLog($id_zonasi, 'jobs_peserta', $msg, "error");
            }
        }
    }
}
