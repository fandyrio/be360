<?php

namespace App\Jobs;

use App\Events\PesertaInsertedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use App\Models\Log_msg;
use App\Models\Trans_observee;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Zonasi_satker;

class InsertDataPesertaZonasi implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $batchData;
    protected $id_zonasi;
    protected $total_batch;
    public function __construct(array $batch_data, int $id_zonasi, int $total_batch)
    {
        $this->batchData=$batch_data;
        $this->id_zonasi=$id_zonasi;
        $this->total_batch=$total_batch;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $get_log=Log_msg::where('data_id', $this->id_zonasi)
                    ->where('category', 'failed_job_peserta')
                    ->where('activity', 'current')
                    ->exists();
        if($get_log){
            return;
        }

        try{
            DB::beginTransaction();
                DB::table('trans_peserta_zonasi')->insert($this->batchData);
            DB::commit();
            event(new PesertaInsertedEvent($this->id_zonasi, $this->total_batch));
        }catch(\Throwable $e){
            DB::rollBack();
            $msg=$e->getMessage();
            $log_msg=new Log_msg;
            $log_msg->data_id=$this->id_zonasi;
            $log_msg->category='failed_job_peserta';
            $log_msg->msg="Job InsertDataPesertaZonasi gagal: ".substr($msg, 0, 550);
            $log_msg->status="error_job";
            $log_msg->save();

            $get_zonasi_satker=Zonasi_satker::where('IdZona', $this->id_zonasi)->pluck('IdZonaSatker');
            Zonasi_satker::where('IdZona', $this->id_zonasi)->update(['entry_job'=>false]);
            Trans_observee::whereIn('IdZonaSatker', $get_zonasi_satker)->update(['entry_job'=>false]);

            Log::error("Job InsertDataPesertaZonasi gagal: " . $e->getMessage());
            throw $e;   
        }
    }
}
