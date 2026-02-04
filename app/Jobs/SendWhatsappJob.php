<?php

namespace App\Jobs;

use App\Models\Log_msg;
use App\Models\Tref_sys_config;
use App\Models\Tref_zonasi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\zonasiService;

class SendWhatsappJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;
    protected $no_wa;
    protected $msg_wa;
    protected $id_zonasi;
    public $tries= 3;
    public $backOff=60;
    /**
     * Create a new job instance.
     */
    public function __construct(string $no_wa, string  $msg_wa, int $id_zonasi){
        $this->no_wa=$no_wa;
        $this->msg_wa=$msg_wa;
        $this->id_zonasi=$id_zonasi;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $key="wa-send-rate";
        $get_config=Tref_sys_config::where('config_name', 'msg_per_minutes')
                        ->first();
        $maxPerMinute=(int)$get_config['config_value_str'];
        if(RateLimiter::tooManyAttempts($key, $maxPerMinute)){
           $retryAfter=RateLimiter::availableIn($key);
           
           self::dispatch($this->no_wa, $this->msg_wa, $this->id_zonasi)
                ->onQueue('send_wa_peserta_'.$this->id_zonasi)
                ->delay(now()->addSeconds($retryAfter +1));

                return;
        }

        RateLimiter::hit($key, 60);
        $zonasiService=resolve(\App\Services\zonasiService::class);
        try{
            $send_wa=sendWa($this->msg_wa, $this->no_wa);
            if($send_wa['status'] === "ok"){
                $get_jobs=Log_msg::where('category', 'jobs_notif')
                            ->where('status', 'prepare')
                            ->where('data_id', $this->id_zonasi)
                            ->first();
                $explode_msg=explode(" ", $get_jobs['msg']);
                $jlh_peserta=$explode_msg[0];

                $get_jobs_progress=Log_msg::where('category', 'jobs_notif')
                                        ->where('status', 'progress')
                                        ->where('data_id', $this->id_zonasi)
                                        ->first();
                if(!is_null($get_jobs_progress)){
                    //update
                    $explode_log=explode(" ", $get_jobs_progress['msg']);
                    $jlh_kirim_pesan=(int)$explode_log[0];
                    $new_jlh_kirim=$jlh_kirim_pesan+=1;
                    $msg_log=$new_jlh_kirim." / ".$jlh_peserta." Notifikasi Link Penilaian Berhasil dikirimkan (Pesan Whatsapp)";
                    
                    $get_jobs_progress->msg=$msg_log;
                    $get_jobs_progress->update();

                    //check jumlah pesan selesai
                    if((int)$jlh_peserta === (int)$new_jlh_kirim){
                        $msg_log="Pengiriman Pesan Whatsapp Kepada Peserta telah Selesai. Max send perminutes: ".$maxPerMinute;
                        $zonasiService->saveLog($this->id_zonasi, "jobs_notif", $msg_log, "finished");

                        $get_zonasi=Tref_zonasi::where('IdZona', $this->id_zonasi)->update(['sent_notif_peserta'=>true]);
                    }
                }else{
                    //insert
                    $msg_log="1 / ".$jlh_peserta." Pesan Whatsapp Berhasil dikirimkan";
                    $zonasiService->saveLog($this->id_zonasi, "jobs_notif", $msg_log, "progress");
                }

            }else{
                $msg_log="Error Mengirimkan Pesan: ".$send_wa['msg'];
                $zonasiService->saveLog($this->id_zonasi, "jobs_notif", $msg_log, "error");
                self::dispatch($this->no_wa, $this->msg_wa, $this->id_zonasi)
                    ->delay(now()->addMinutes(2))
                    ->onQueue('send_wa_peserta_'.$this->id_zonasi);
            }
        }catch(\Throwable $e){
            $msg_log="Pengiriman Pesan kepada no ".$this->no_wa." tidak dapat dilakukan. ".$e->getMessage();
            $zonasiService->saveLog($this->id_zonasi, "jobs_notif", $msg_log, "error");

            self::dispatch($this->no_wa, $this->msg_wa, $this->id_zonasi)
                    ->delay(now()->addMinutes(2))
                    ->onQueue('send_wa_peserta_'.$this->id_zonasi);
        }
        
    }
}
