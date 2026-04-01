<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Tahun_penilaian;
use App\Models\Trans_jabatan_kosong;
use App\Models\Zonasi_satker;
use App\Services\dashboardService;
use Illuminate\Http\Request;

class dashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(dashboardService $dashboard_service)
    {
        $this->dashboardService=$dashboard_service;
    }

    public function dashboardAdminSatker(Request $request){
        $role=$request->user()->IdRole;
        $data=[];
        $ada_jabatan_kosong=false;
        $jlh_penilaian=0;
        $blm_kirim_penilaian=false;
        if($role === 3 || $role === 4){
            if(!checkDataAdminSatker($request->user()->uname)){
                return response()->json(['status'=>false, 'msg'=>'Silahkan Melengkapi data Admin Terlebih dahulu']);
            }else{
                $satker_id=$request->user()->IdSatker;
                $get_data=$this->dashboardService->getDataDashboardSatker($satker_id);
                $ada_jabatan_kosong=$get_data['ada_jabatan_kosong'];
                $blm_kirim_penilaian=$get_data['blm_kirim_penilaian'];
                $jlh_penilaian=$get_data['jlh_penilaian'];
                return response()->json(['status'=>true, 'data'=>['ada_jabatan_kosong'=>$ada_jabatan_kosong, 'blm_kirim_penilaian'=>$blm_kirim_penilaian, 'jlh_penilaian'=>$jlh_penilaian]]);
            }
        }
        
        // return response()->json(['status'=>true, 'msg'=>"Access Valid", 'data'=>[]]);
    }

    public function dashboardAdminBadilum($refresh = null){
        //get jumlah periode
        $jumlah_periode=$this->dashboardService->getAllPeriode()->count();
        $running_periode=$this->dashboardService->runningPeriode();
        $rata_rata=$this->dashboardService->getRataRataAll($refresh);
        $data_avg=[];
        foreach($rata_rata as $list_rata_rata){
            $data_avg[]=[
                "variable"=>$list_rata_rata["variable"],
                "rata_rata"=>$list_rata_rata["rata_rata"]
            ];
        }
        

        return response()->json(["jumlah_periode"=>$jumlah_periode, "running_periode"=>$running_periode->tahun, "rata_rata"=>$data_avg]);
    }
}
