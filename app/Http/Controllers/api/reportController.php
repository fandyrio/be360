<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Services\periodeService;
use App\Services\reportService;
use App\Services\zonasiSatkerService;
use App\Services\zonasiService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

class reportController extends Controller
{
    protected $reportService;
    protected $periodeService;
    protected $zonasiService;
    protected $zonasiSatkerService;
    public function __construct(reportService $report_service, periodeService $periode_service, zonasiService $zonasi_service, zonasiSatkerService $zonasi_satker_service)
    {
        $this->reportService=$report_service;
        $this->periodeService=$periode_service;
        $this->zonasiService=$zonasi_service;
        $this->zonasiSatkerService=$zonasi_satker_service;
    }
    public function getListPeriode(){
        $data=[];$x=0;
        $status=false;
        $msg="Data tidak ditemukan";
        $list_periode=$this->periodeService->getAllPeriode();
        foreach($list_periode as $periode){
            $data[$x]['token_periode']=Hashids::encode($periode['IdTahunPenilaian']);
            $data[$x]['nama_periode']=$periode['tahun']." - ".$periode['keterangan'];
            $x++;
        }
        if($x > 0){
            $status=true;
        }
        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function getZonasiPeriode($token_periode){
        $data=[];
        $status=false;
        $msg="Data tidak ditemukan";
        $periode_id=Hashids::decode($token_periode);
        if(!empty($periode_id)){
            $data=$this->zonasiService->getZonasiByPeriode($periode_id[0]);
            if(count($data) > 0){
                $status=true;
                $msg="";
            }
        }else{
            $msg="Data tidak konsisten";
        }
        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function getZonasiSatkerService($token_periode, $token_zonasi){
        $data=[];
        $status=false;
        $msg="";
        $periode_id=Hashids::decode($token_periode);
        $id_zonasi=Hashids::decode($token_zonasi);
        if(!empty($id_zonasi) && !empty($periode_id)){
            $data=$this->zonasiSatkerService->getZonasiSatkerByPeriodeZonasi($periode_id[0], $id_zonasi[0]);
            if(count($data) > 0){
                $status=true;
            }else{
                $msg="Data tidak ditemukan";
            }
        }else{
            $msg="Data tidak konsisten";
        }
        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }
    public function reportSatker(Request $request){
        $refresh=false;
        $data=[];
        $status=false;
        try{
            $request->validate([
                'token_periode'=>['required', 'string'],
                'token_zonasi'=>['required', 'string'],
                'token_zonasi_satker'=>['required', 'string']
            ]);
            try{
                if(isset($request->refresh) && $request->refresh === true){
                    $refresh=true;
                }
                $periode_id=Hashids::decode($request->token_periode);
                $zonasi_id=Hashids::decode($request->token_zonasi);
                $zonasi_satker_id=Hashids::decode($request->token_zonasi_satker);
                if(empty($zonasi_id) || empty($zonasi_satker_id) || empty($periode_id)){
                    throw new \Exception("Data tidak valid");
                }
                $report=$this->reportService->generateDataReport($zonasi_satker_id[0], $zonasi_id[0], $periode_id[0], $refresh);
                $status=$report['status'];
                $msg=$report['msg']." ".$zonasi_satker_id[0]." ".$zonasi_id[0]." ".$periode_id[0];
                $data=$report['data'];
                $status=true;
            }catch(\Exception $e){
                $msg=$e->getMessage()." ".$e->getFile();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }
}
