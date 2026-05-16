<?php

namespace App\Http\Controllers;

use App\Services\periodeService;
use App\Services\zonasiService;
use Illuminate\Http\Request;

class monitoringController extends Controller
{
    protected $monitoringService;
    protected $periodeService;
    protected $zonasiService;

    public function __construct(periodeService $periode_service, zonasiService $zonasi_service)
    {
        // throw new \Exception('Not implemented');
        $this->periodeService = $periode_service;
        $this->zonasiService = $zonasi_service;
    }

    public function index(){
        $zonasi = $this->zonasiService->listZonasi(1);
        return view("monitor", ['zonasi'=>$zonasi['data'], 'jumlah'=>count($zonasi['data'])]);
    }
}
