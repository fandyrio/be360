<?php

namespace App\Http\Controllers;

use App\Services\monitoringService;
use App\Services\periodeService;
use App\Services\zonasiService;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

class monitoringController extends Controller
{
    protected $monitoringService;
    protected $periodeService;
    protected $zonasiService;

    public function __construct(periodeService $periode_service, zonasiService $zonasi_service, monitoringService $monitoring_service)
    {
        // throw new \Exception('Not implemented');
        $this->periodeService = $periode_service;
        $this->zonasiService = $zonasi_service;
        $this->monitoringService = $monitoring_service;
    }

    public function index(){    
        $zonasi = $this->zonasiService->listZonasi(1);
        return view("monitor", ['zonasi'=>$zonasi['data'], 'jumlah'=>count($zonasi['data'])]);
    }

    public function listNotFound(Request $request){
        $zonasi_id = Hashids::decode($request->zonasi);
        $get_not_found = $this->monitoringService->getLinkNotFound($zonasi_id);
        return view("monitoring/list_not_found", ['data'=>$get_not_found['data'], 'jumlah'=>$get_not_found['jumlah'], 'title'=> 'Link 404', 'class_fix'=>'fix']);
    }

    public function listDouble(Request $request){
        $zonasi_id = Hashids::decode($request->zonasi);
        $get_list_double = $this->monitoringService->getDoubleInsert($zonasi_id);
        return view("monitoring/list_not_found", ['data'=>$get_list_double['data'], 'jumlah'=>$get_list_double['jumlah'], 'title'=> 'Double Insert', 'class_fix'=>'fix_double']);
    }

    public function fixNotFound(Request $request){
        $id_observee = Hashids::decode($request->target);
        $fix = $this->monitoringService->fix404($id_observee[0]);
        $status = $fix['status'];
        $msg = $fix['msg'];
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }
}
