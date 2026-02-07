<?php
    namespace App\Services;

use App\Jobs\InsertDataPesertaZonasi;
use App\Models\Jobs;
use App\Models\Log_msg;
use App\Models\Tref_users;
    use App\Models\Tref_zonasi;
    use Vinkla\Hashids\Facades\Hashids;
    use App\Models\Satker;
    use App\Models\Tahun_penilaian;
    use Illuminate\Support\Facades\DB;
    use App\Models\Zonasi_satker;
    use App\Models\Tref_pegawai;
    use App\Models\Trans_observee;
use App\Models\Tref_jabatan_peserta;
use App\Models\Tref_mapping_jabatan;
use App\Models\Trans_jabatan_kosong;
use App\Models\Trans_peserta_zonasi;
use App\Models\V_total_peserta_satker;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\CssSelector\Node\HashNode;

    class zonasiService{
        public function listZonasi($page){
            $data=[];
            $limit=30;
            $total=Tref_zonasi::count();
            $jumlahHalaman=ceil($total / $limit);
            if($page < 1 || $page > $jumlahHalaman || is_null($page)){
                $page=1;
            }
            $skip=$page * $limit - $limit;
            if($total > 0){
                $get_data=Tref_zonasi::join('tref_tahun_penilaian', 'tref_tahun_penilaian.IdTahunPenilaian', '=', 'tref_zonasi.IdTahunPenilaian')
                                    ->select('tref_zonasi.*', 'tref_tahun_penilaian.keterangan', 'tref_tahun_penilaian.tahun')
                                    ->orderBy('tref_zonasi.IdZona', 'desc')
                                    ->skip($skip)->take($limit)->get();
                $x=0;
                foreach($get_data as $list_data){
                    $data[$x]['enc_id']=Hashids::encode($list_data['IdZona']);
                    $data[$x]['nama_zona']=$list_data['nama_zona'];
                    $data[$x]['periode']=$list_data['keterangan'];
                    $data[$x]['start_date']=$list_data['start_date'];
                    $data[$x]['end_date']=$list_data['end_date'];
                    $data[$x]['tahun_penilaian']=$list_data['tahun'];
                    $data[$x]['is_active']=$list_data['is_active'];
                    $x++;
                }
            }

            return [
                'page'=>$page,
                'jumlah_halaman'=>$jumlahHalaman,
                'total'=>$total,
                'start_number'=>$skip+1,
                'data'=>$data
            ];
        }

        public function preparationZonasi($id_satker_selected){
            //1. Check User apakah sudah seluruh nya melengkapi data
            // $get_data=Tref_users::join('v_satker as vs', 'vs.KodeSatker', '=', 'tref_users.uname')
            //                 ->
        }

        public function saveZonasi($request, $id_tahun_penilaian){
            $save=false;
            $msg="";
            $get_data=Tahun_penilaian::where('IdTahunPenilaian', $id_tahun_penilaian)
                            ->where('proses_id', '<=', 4)
                            ->first();
            if(!is_null($get_data)){
                //check zonasi yang masih pending penggunaan
                $validate_user=Tref_users::where('IdPegawai', 0)->count();
                if($validate_user > 0){
                    return ['status'=>false, 'msg'=>"Belum bisa menambahkan Zonasi. Admin Satker masih ada yang belum melengkapi data"];
                }
                try{
                    DB::beginTransaction();
                    //1. Save Zonasi
                        $zonasi=new Tref_zonasi;
                        $zonasi->nama_zona=$request->nama_zona;
                        $zonasi->start_date=$request->start_date;
                        $zonasi->end_date=$request->end_date;
                        $zonasi->IdTahunPenilaian=$id_tahun_penilaian;
                        $zonasi->is_active=$request->is_active === "Y" ? true : false;
                        $zonasi->diinput_tgl=date('Y-m-d H:i:s');
                        $zonasi->diinput_oleh=$request->user()->uname;
                        $zonasi->save();

                        $id_zonasi=$zonasi->IdZona;
                        $jlh_satker=count($request->id_satker);
                        $id_satker_selected=array();
                        $error=false;

                        for($x=0;$x<$jlh_satker;$x++){
                            $id_satker=Hashids::decode($request->id_satker[$x]);
                            if(empty($id_satker)){
                                $error=true;
                                $msg.="Satker tidak dikenali";
                                break;
                            }
                            $id_satker_selected[]=$id_satker[0];
                        }
                       
                        if($error){
                            DB::rollBack();
                        }else{
                            $check_existed_satker=$this->checkExistedSatkerOnPeriode($id_tahun_penilaian, $id_satker_selected);
                            if($check_existed_satker){
                                $get_satker=Satker::join('v_total_peserta_per_satker as a', 'a.IdSatker', '=', 'v_satker.IdSatker')
                                        ->select('v_satker.*', 'a.jumlah_peserta', 'a.jumlah_panmud')
                                        ->whereIn('v_satker.IdSatker', $id_satker_selected)->get();
                                $data=[];
                                foreach($get_satker as $list_satker){
                                    $data[]=[
                                        'IdZona'=>$id_zonasi,
                                        'IdSatkerBanding'=>$list_satker['IdBanding'],
                                        'IdSatker'=>$list_satker['IdSatker'],
                                        'jumlah_personil'=>(int)$list_satker['jumlah_peserta']-=1,
                                        'total_panmud'=>(int)$list_satker['jumlah_panmud'],
                                        'diinput_tgl'=>date('Y-m-d H:i:s'),
                                        'diinput_oleh'=>$request->user()->uname, 
                                    ];
                                }
                                //2. Save Satker Peserta
                                DB::table('trans_zonasi_satker')->insert($data);
                                //3. Save Data Peserta
                                $data_peserta=$this->updatePesertaSIKEP($id_zonasi);
                                if($data_peserta !== true){
                                    DB::rollBack();
                                    $msg="Data satker tidak ditemukan ";
                                }else{
                                    $save=true;
                                    $msg="Berhasil menyimpan Data Awal Zonasi ";
                                    $generate_peserta=$this->getPeserta(Hashids::encode($id_zonasi));
                                    $msg.=" dan ".$generate_peserta['msg'];
                                }
                            }else{
                                DB::rollBack();
                                $msg="Ada Satker telah didaftarkan dizonasi lain dalam periode yang sama";
                            }
                        }
                    DB::commit();
                }catch(\Exception $e){
                    DB::rollback();
                    $msg.="Terjadi kesalahan sistem saat menyimpan zonasi ".$e->getFile()." : ".$e->getMessage()." : ".$e->getLine()." : ";
                    Log::error("Job InsertDataPesertaZonasi gagal: ", ['trace'=>$e->getTrace()]);
                }
            }else{
                $msg="Tahun penilaian tidak ditemukan atau Periode sudah Mulai";
            }

            return [
                'status'=>$save,
                'msg'=>$msg
            ];
        }

        public function addSatkerToZonasi($request, $id_zonasi){
            $status=false;
            $get_zonasi=Tref_zonasi::where('IdZona', $id_zonasi)
                            ->whereRaw('proses_id <= 2')
                            ->first();
            if(!is_null($get_zonasi)){
                $jlh_satker=count($request->id_satker);
                $id_satker_selected=[];
                $error=false;
                for($x=0;$x<$jlh_satker;$x++){
                    $id_satker=Hashids::decode($request->id_satker[$x]);
                    if(empty($id_satker)){
                        $error=true;
                        $msg="Data satker tidak valid";
                        break;
                    }
                    $id_satker_selected[]=$id_satker[0];
                }
                if($error === false){
                    $check_existed_satker=$this->checkExistedSatkerOnPeriode($get_zonasi['IdTahunPenilaian'], $id_satker_selected);
                    if($check_existed_satker){
                        try{
                            DB::beginTransaction();
                                $get_satker=Satker::join('v_total_peserta_per_satker as a', 'a.IdSatker', '=', 'v_satker.IdSatker')
                                            ->select('v_satker.*', 'a.jumlah_peserta', 'a.jumlah_panmud')
                                            ->whereIn('v_satker.IdSatker', $id_satker_selected)
                                            ->get();
                                $data=[];
                                foreach($get_satker as $list_satker){
                                    $data[]=[
                                        'IdZona'=>$id_zonasi,                                        
                                        'IdSatkerBanding'=>$list_satker['IdBanding'],
                                        'IdSatker'=>$list_satker['IdSatker'],
                                        'jumlah_personil'=>$list_satker['jumlah_peserta'],
                                        'total_panmud'=>$list_satker['jumlah_panmud'],
                                        'diinput_tgl'=>date('Y-m-d H:I:s'),
                                        'diinput_oleh'=>$request->user()->uname,
                                    ];
                                }
                                DB::table('trans_zonasi_satker')->insert($data);
                                $data_peserta=$this->updatePesertaSIKEP($id_zonasi, $id_satker_selected);
                                if($data_peserta === false){
                                    DB::rollBack();
                                    $msg="Data satker tidak ditemukan";
                                }else{
                                    DB::commit();
                                    $status=true;
                                    $msg="Berhasil menambahkan satker";
                                }
                        }catch(\Exception $e){
                            DB::rollBack();
                            $msg=$e->getMessage();
                        }
                    }else{
                        $msg="Ada satker yang telah ditambahkan pada zonasi lain pada periode ini";
                    }
                }
            }else{
                $msg="Data zonasi tidak ditemukan atau Zonasi ini sudah tidak bisa menambah satker baru";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        

        public function updatePesertaSIKEP($id_zonasi, $id_satker_selected = null){
            $data=[];
            $data_observee=[];
            // $id_kelompok_kepaniteraan=[9, 16, 30, 31, 32, 15, 27];
            $id_kelompok_kepaniteraan=[];
            $get_jabatan_peserta=Tref_jabatan_peserta::where('active', true)->get();
            foreach($get_jabatan_peserta as $list_jabatan_peserta){
                $id_kelompok_kepaniteraan[]=$list_jabatan_peserta['id_kelompok_jabatan'];
            }
            if(is_null($id_satker_selected)){
                $id_satker_selected=$this->getSatkerOnZonasi($id_zonasi);
            }
            // $id_satker_selected=$data_satker['satker'];
            $jlh_satker=count($id_satker_selected);
            if($jlh_satker > 0){
                $selected_satker=implode(",", $id_satker_selected);
                $kelompok_jabatan=implode(",", $id_kelompok_kepaniteraan);
                $get_data=DB::select("CALL SPGetPeserta360('$selected_satker', '$kelompok_jabatan', '$id_zonasi')");
                $x=0;
                $pegawai_sikep=[];
                foreach($get_data as $list_peserta){
                    $data[$x]['id_pegawai']=$list_peserta->IdPegawai;
                    $data[$x]['nama_pegawai']=$list_peserta->NamaLengkap;
                    $data[$x]['nip']=$list_peserta->NIPBaru;
                    $data[$x]['status_pegawai']=$list_peserta->StatusPegawai;
                    $data[$x]['no_hp']=$list_peserta->NomorHandphone;
                    $data[$x]['foto_pegawai']=$list_peserta->FotoPegawai;
                    
                    $id_nama_jabatan_new=(int)$list_peserta->IdNamaJabatan + (int)$list_peserta->IdPegawai;
                    $id_zona_satker_new = (int)$list_peserta->IdZonaSatker + (int)$list_peserta->IdPegawai;
                    $enc_id_pegawai=Hashids::encode($list_peserta->IdPegawai);
                    $enc_id_nama_jabatan=Hashids::encode($id_nama_jabatan_new);
                    $enc_id_zona_satker=Hashids::encode($id_zona_satker_new);

                    $data_observee[$x]['IdPegawai']=$list_peserta->IdPegawai;
                    $data_observee[$x]['NIPBaru']=$list_peserta->NIPBaru;
                    $data_observee[$x]['id_kelompok_jabatan']=$list_peserta->IdKelompokJabatan;
                    $data_observee[$x]['IdNamaJabatan']=$list_peserta->IdNamaJabatan;
                    $data_observee[$x]['NamaJabatan']=$list_peserta->NamaJabatan;
                    $data_observee[$x]['IdZonaSatker']=$list_peserta->IdZonaSatker;
                    $data_observee[$x]['endpoint']=$enc_id_pegawai."-".$enc_id_nama_jabatan."-".$enc_id_zona_satker;
                    $data_observee[$x]['diinput_tgl']=date('Y-m-d H:i:s');
                    $data_observee[$x]['diinput_oleh']="system";
                    $pegawai_sikep[]=$list_peserta->IdPegawai;
                    $x++;
                }

                $get_pegawai=Tref_pegawai::get();
                $pegawai_existed=[];
                foreach($get_pegawai as $list_pegawai){
                    $pegawai_existed[]=$list_pegawai['id_pegawai'];
                }

                $lookup=array_flip($pegawai_existed);
                $jlh_sikep=count($pegawai_sikep);
                
                $data_insert=[];
                $data_insert_observee=[];
                $index_observee=0;
                for($x=0;$x<$jlh_sikep;$x++){
                    if(!isset($lookup[$pegawai_sikep[$x]])){
                        $data_insert[]=$data[$x];
                    }
                    $data_insert_observee[] = $data_observee[$x];
                }

                DB::table('tref_pegawai')->insert($data_insert);
                DB::table('trans_observee')->insert($data_insert_observee);
            }else{
                return false;
            }
            return true;
        }

        public function checkExistedSatkerOnPeriode($id_tahun_penilaian, $id_satker_selected){
            $get_existed_satker=$this->getSatkerOnPeriode($id_tahun_penilaian);
            // $get_zonasi=Tref_zonasi::where('IdTahunPenilaian', $id_tahun_penilaian)->get();
            if($get_existed_satker === "true"){
                return true;
            }
            $satker=$get_existed_satker['satker'];
            if(count(array_intersect($satker, $id_satker_selected)) > 0){
                return false;
            }
            return true;
        }

        public function getSatkerOnPeriode($id_tahun_penilaian){
            $get_zonasi=Tref_zonasi::where('IdTahunPenilaian', $id_tahun_penilaian)->get();
            $jumlah_zonasi=$get_zonasi->count();
            if($jumlah_zonasi === 0){
                return "true";
            }
            $id_zonasi=array();
            foreach($get_zonasi as $list_zonasi){
                $id_zonasi[]=$list_zonasi['IdZona'];
            }
            $get_zonasi_satker=Zonasi_satker::whereIn('IdZona', $id_zonasi)->get();
            $satker=array();
            $id_zona_satker=[];
            foreach($get_zonasi_satker as $list_satker){
                $satker[]=$list_satker['IdSatker'];
                $id_zona_satker[]=$list_satker['IdZonaSatker'];
            }

            return [
                'satker'=>$satker,
                'id_zona'=>$id_zona_satker
            ];
        }

        public function getSatkerOnZonasi($id_zonasi){
            $get_zonasi_satker=Zonasi_satker::where('IdZona', $id_zonasi)->get();
            $satker=[];
            foreach($get_zonasi_satker as $list_satker){
                $satker[]=$list_satker['IdSatker'];
            }
            return $satker;
        }


        public function getZoneById($id){
            $data=[];
            $view['send_notif']=false;
            $view['monitoring']=false;
            $status=false;
            $msg="";
            $signature="";
            $get_data=Tref_zonasi::join('tref_tahun_penilaian as a', 'a.IdTahunPenilaian', '=', 'tref_zonasi.IdTahunPenilaian')
                        ->join('tref_tahapan_proses as ttp', 'ttp.id', '=', 'tref_zonasi.proses_id')
                        ->select('tref_zonasi.*', 'a.tahun', 'ttp.proses')
                        ->where('IdZona', $id)->first();
            if(!is_null($get_data)){
                $status=true;
                $proses_id=(int)$get_data['proses_id'];
                $data['enc_id']=Hashids::encode($get_data['IdZona']);
                $data['nama_zona']=$get_data['nama_zona'];
                $data['start_date']=$get_data['start_date'];
                $data['end_date']=$get_data['end_date'];
                $data['periode']=$get_data['tahun'];
                $data['proses_id']=$proses_id;
                $data['proses_text']=$get_data['proses'];
                $data['periode_id']=Hashids::encode($get_data['IdTahunPenilaian']);
                $data['is_active']=(boolean)$get_data['is_active'] === true ? 'Y' : 'N';

                if(($proses_id === 5 || $proses_id === 6)){
                    $view['send_notif']=true;
                    if((int)$get_data['sent_notif_peserta'] === 1){
                        $view['monitoring']=true;
                    }
                }

                $payload=json_encode(['payload'=>Hashids::encode($get_data['IdZona'])]);
                $secret=config('app.hmac_secret');
                $signature=hash_hmac('sha256', $payload, $secret);
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data,
                'view'=>$view
            ];
        }

        public function getSatkerZonasi($id_zona){
            $status=false;
            $msg="";
            $data_satker=[];
            $regenerate=false;
            $run_job=true;

            $sub_query=Trans_peserta_zonasi::selectRaw("count(*)")
                        ->whereColumn('trans_peserta_zonasi.id_zona_satker', 'trans_zonasi_satker.IdZonaSatker');

            $get_satker=Zonasi_satker::query()
                    ->join('v_total_peserta_per_satker as s', 's.IdSatker', '=', 'trans_zonasi_satker.IdSatker')
                    ->select('trans_zonasi_satker.*', 's.NamaSatker', 's.jumlah_peserta')
                    ->selectSub($sub_query, 'jumlah_penilaian')
                    ->where('IdZona', $id_zona)
                    ->orderBy('trans_zonasi_satker.IdSatkerBanding', 'asc')
                    ->orderByRaw('(trans_zonasi_satker.IdSatkerBanding = trans_zonasi_satker.IdSatker) desc')
                    ->orderBy('trans_zonasi_satker.IdSatker', 'asc')
                    ->get();
            $jumlah_data=$get_satker->count();
            if($jumlah_data > 0){
                $status=true;
                $x=0;
                $index_banding=0;
                $index_pn=0;
                $id_banding_before = 0;
                $data_satker=[];
                $reset_to_banding=false;
                foreach($get_satker as $list_satker){
                    if($list_satker['entry_job'] === 0 && $run_job === true){
                        $run_job=false;
                        $regenerate=true;
                    }
                    if((int)$id_banding_before !==(int)$list_satker['IdSatkerBanding']){
                        $index_pn=0;
                        if((int)$list_satker['IdSatkerBanding'] === (int)$list_satker['IdSatker']){
                            $data_satker[$index_banding]['enc_id_zonasi_satker']=Hashids::encode($list_satker['IdZonaSatker']);
                            $data_satker[$index_banding]['nama_satker_banding']=$list_satker['NamaSatker'];
                            $data_satker[$index_banding]['enc_id_banding']=Hashids::encode($list_satker['IdSatker']);
                            $data_satker[$index_banding]['jumlah_penilaian']=$list_satker['jumlah_penilaian'];
                            $data_satker[$index_banding]['jumlah_peserta']=$list_satker['jumlah_peserta'];
                            $data_satker[$index_banding]['satker_pn']=[];
                            $data_satker_pn = &$data_satker[$index_banding]['satker_pn'];
                        }else{
                            $data_satker[$index_banding]['enc_id_zonasi_satker']=null;
                            $data_satker[$index_banding]['nama_satker_banding']=null;
                            $data_satker[$index_banding]['enc_id_banding']=null;
                            $data_satker[$index_banding]['jumlah_penilaian']=null;
                            $data_satker[$index_banding]['jumlah_peserta']=null;
                            $data_satker[$index_banding]['satker_pn']=[];
                            $data_satker_pn = &$data_satker[$index_banding]['satker_pn'];
                            $data_satker_pn[$index_pn]['enc_id_zonasi_satker']=Hashids::encode($list_satker['IdZonaSatker']);
                            $data_satker_pn[$index_pn]['nama_satker_pn']=$list_satker['NamaSatker'];
                            $data_satker_pn[$index_pn]['enc_id_pn']=Hashids::encode($list_satker['IdSatker']);
                            $data_satker_pn[$index_pn]['jumlah_penilaian']=$list_satker['jumlah_penilaian'];
                            $data_satker_pn[$index_pn]['jumlah_peserta']=$list_satker['jumlah_peserta'];
                            $index_pn+=1;
                        }
                        
                        $index_banding+=1;
                        $reset_to_banding=true;
                    }else{
                        $data_satker_pn[$index_pn]['enc_id_zonasi_satker']=Hashids::encode($list_satker['IdZonaSatker']);
                        $data_satker_pn[$index_pn]['nama_satker_pn']=$list_satker['NamaSatker'];
                        $data_satker_pn[$index_pn]['enc_id_pn']=Hashids::encode($list_satker['IdSatker']);
                        $data_satker_pn[$index_pn]['jumlah_penilaian']=$list_satker['jumlah_penilaian'];
                        $data_satker_pn[$index_pn]['jumlah_peserta']=$list_satker['jumlah_peserta'];
                        $index_pn+=1;
                        $reset_to_banding=false;
                        
                    }
                    

                    $id_banding_before=(int)$list_satker['IdSatkerBanding'];
                    $x++;
                }
            }else{
                $msg="Data satker tidak ada";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'run_job'=>$run_job,
                'regenerate'=>$regenerate,
                'data'=>$data_satker
            ];
        }

        public function getDataSatkerLengkap(){
            $get_active_periode=Tahun_penilaian::where('proses_id', 1)->first();
            $satker=[];
            if(!is_null($get_active_periode)){
                //get satker pada periode yang aktif
                $data_satker=$this->getSatkerOnPeriode($get_active_periode['IdTahunPenilaian']);

                if($data_satker !== "true"){
                    $satker[]=$data_satker['satker'];
                }
            }
           
            $get_pt=Cache::store('redis')->remember("satker_pt", 3600*24*360, function () {
                return Satker::join('v_total_peserta_per_satker as a', 'a.IdSatker', '=', 'v_satker.IdSatker')
                            ->select('v_satker.*', 'a.jumlah_peserta')
                            ->where('v_satker.ParentIdSatker', 3)->get();  
            });
            $total_pt=$get_pt->count();
            $x=0;
            $data=[];
            foreach($get_pt as $list_satker){
                $disabled_pt=false;
                $parent_satker=$list_satker['IdSatker'];

                $get_pn=Cache::store('redis')->remember("satker_pn_{$parent_satker}", 3600*24*360, function () use($parent_satker) {
                    return Satker::join('v_total_peserta_per_satker as a', 'a.IdSatker', '=', 'v_satker.IdSatker')
                            ->select('v_satker.*', 'a.jumlah_peserta')
                            ->where('v_satker.ParentIdSatker', $parent_satker)->get(); 
                });
                if(in_array($list_satker['IdSatker'], $satker)){
                    $disabled_pt=true;
                }
                $data[$x]['satker_banding']=$list_satker['NamaSatker'];
                $data[$x]['kode_banding']=$list_satker['KodeSatker'];
                $data[$x]['jumlah_peserta']=$list_satker['jumlah_peserta'];
                // $data[$x]['id']=$list_satker['IdSatker'];
                $data[$x]['enc_id']=Hashids::encode($list_satker['IdSatker']);
                $data[$x]['disabled']=$disabled_pt;
                $y=0;
                $data[$x]['jumlah_pn']=$get_pn->count();
                foreach($get_pn as $list_pn){
                    $disabled_pn=false;
                    if(in_array($list_pn['IdSatker'], $satker)){
                        $disabled_pn=true;
                    }
                    $data[$x]['satker_pn'][$y]['nama']=$list_pn['NamaSatker'];
                    $data[$x]['satker_pn'][$y]['kode_banding']=$list_satker['KodeSatker'];
                    $data[$x]['satker_pn'][$y]['kode']=$list_pn['KodeSatker'];
                    $data[$x]['satker_pn'][$y]['jumlah_peserta']=$list_pn['jumlah_peserta'];
                    $data[$x]['satker_pn'][$y]['enc_id']=Hashids::encode($list_pn['IdSatker']);
                    $data[$x]['satker_pn'][$y]['disabled']=$disabled_pn;
                    // $data[$x]['satker_pn'][$y]['id']=$list_pn['IdSatker'];
                    $y++;
                }
                $x++;
            }
            return [
                'total_banding'=>$total_pt,
                'data'=>$data
            ];
        }

        public function removeExistedSatker($request, $id_zonasi){
            $status=false;
            $error=false;
            $remove_peserta=null;
            $get_zonasi=Tref_zonasi::where('IdZona', $id_zonasi)
                            ->whereRaw('proses_id <= 2')
                            ->first();
            if(!is_null($get_zonasi)){
                $get_satker=Zonasi_satker::where('IdZona', $id_zonasi)->get();
                $id_existed_satker=[];
                $id_zonasi_satker=[];
                foreach($get_satker as $list_satker){
                    $id_existed_satker[]=$list_satker['IdSatker'];
                    $id_zonasi_satker[]=$list_satker['IdZonaSatker'];
                }
                $jlh_selected_satker=count($request->id_satker);
                $id_satker=[];
                for($x=0;$x<$jlh_selected_satker;$x++){
                    $id_satker_dec=Hashids::decode($request->id_satker[$x]);
                    if(empty($id_satker_dec)){
                        $error=true;
                        throw new \Exception('Invalid token');
                    }
                    $id_satker[]=$id_satker_dec[0];
                }
                $lookup=array_flip($id_satker);
                $removed_id=[];
                $jlh_existed_satker=count($id_existed_satker);
                for($x=0;$x<$jlh_existed_satker;$x++){
                    if(!isset($lookup[$id_existed_satker[$x]])){
                        $removed_id[]=$id_zonasi_satker[$x];
                    }
                }
                
                if($error === false){
                    $jlh_removed=count($removed_id);
                    if($jlh_removed > 0){
                        $remove_peserta=$this->removeDataSatkerPeserta($removed_id);
                        Log_msg::where('data_id', $id_zonasi)->update(['activity' => 'past']);
                        Jobs::query()->delete();
                        Trans_observee::whereIn('IdZonaSatker', $id_zonasi_satker)->update(['entry_job' => false]);
                        Zonasi_satker::where('IdZona', $id_zonasi)->update(['entry_job' => false]);
                        $status=$remove_peserta['status'];
                        $msg=$remove_peserta['msg'];
                    }else{
                        $msg="Tidak ada perubahan data ";
                    }
                }
            }else{
                $msg="Data zonasi tidak dapat diubah lagi";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function removeDataSatkerPeserta($id_zonasi_satker){
            $status=false;
            $msg="";
            
            try{
                DB::beginTransaction();
                    Trans_observee::whereIn('IdZonaSatker', $id_zonasi_satker)->delete();
                    Zonasi_satker::whereIn('IdZonaSatker', $id_zonasi_satker)->delete();
                    Trans_peserta_zonasi::whereIn('id_zona_satker', $id_zonasi_satker)->delete();
                    $status=true;
                    $msg="Berhasil menghapus data";
                DB::commit();
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function regeneratePeserta($id_zonasi){
            $status=false;
            $check_zonasi=Tref_zonasi::where('IdZona', $id_zonasi)->first();
            if(!is_null($check_zonasi)){
                $check_entry_job=Zonasi_satker::where('IdZona', $id_zonasi)
                                    ->where('entry_job', false)
                                    ->first();
                if(!is_null($check_entry_job)){
                    $get_peserta=$this->getPeserta(Hashids::encode($id_zonasi));
                    $status=$get_peserta['status'];
                    $msg="Msg: ".$get_peserta['msg'];
                }else{
                    $msg="Seluruh data telah digenerate. Silahkan Jalankan Antrian";
                }
            }else{
                $msg="Data Zonasi tidak ditemukan";
            }
            
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getPeserta($id_zonasi_enc){
            ini_set('memory_limit', '2G');
            $status=false;
            $id_zonasi_dec=Hashids::decode($id_zonasi_enc);
            if(empty($id_zonasi_dec)){
                return [
                    'status'=>$status,
                    'msg'=>"Invalid Token Zonasi"
                ];
            }
            $id_zonasi=$id_zonasi_dec[0];
            Log_msg::where('data_id', $id_zonasi)->update(['activity'=>'past']);
            $variable_jabatan_peserta_arr=[];
            $id_jabatan_peserta_arr=[];
            $id_jabatan_kosong=[];
            $get_satker_zonasi=Zonasi_satker::join('v_satker as vs', 'vs.IdSatker', '=', 'trans_zonasi_satker.IdSatker')
                                        ->join('v_total_peserta_per_satker as vtps', 'vtps.IdSatker', '=', 'vs.IdSatker')
                                        ->select('trans_zonasi_satker.*', 'vs.NamaSatker', 'vtps.jumlah_panmud')
                                        // ->where('trans_zonasi_satker.IdSatker', $id_satker)
                                        ->where('IdZona', $id_zonasi)
                                        ->where('entry_job', false)
                                        ->orderBy('IdZona', 'desc')->get();
            $getPeserta=Trans_observee::join('trans_zonasi_satker as tzs', function($join) use ($id_zonasi){
                                                    $join->on('tzs.IdZonaSatker', '=', 'trans_observee.IdZonaSatker')
                                                        // ->where('tzs.IdSatker', $id_satker)
                                                        ->where('tzs.IdZona', $id_zonasi);
                                                })
                                        ->join('tref_pegawai', 'tref_pegawai.id_pegawai', '=', 'trans_observee.IdPegawai')
                                        ->select('trans_observee.*', 'tref_pegawai.nama_pegawai', 'tref_pegawai.nip', 'tref_pegawai.status_pegawai', 'tzs.IdZonaSatker')
                                        ->orderBy('trans_observee.IdZonaSatker', 'desc')
                                        ->where('trans_observee.entry_job', false)
                                        ->get();
            // $jabatan_teknis=[9, 16, 30, 31, 32, 15, 27];
            $get_jabatan_peserta=Tref_jabatan_peserta::where('active', true)->get();
            $index_satker=0;
            $satker=[];
            $id_satker=[];
            $satker_pimpinan_kosong=[];
            $is_pt=[];
            $id_zonasi_satker=[];
            foreach($get_satker_zonasi as $list_satker){
                $satker[]=$list_satker['NamaSatker'];
                $id_satker[]=$list_satker['IdSatker'];
                $id_zonasi_satker[]=$list_satker['IdZonaSatker'];
                if($list_satker['IdSatkerBanding'] === $list_satker['IdSatker']){
                    $is_pt[]="true";
                }else{
                    $is_pt[]="false";
                }
                $data_peserta[$index_satker]['nama_satker']=$list_satker['NamaSatker'];
                $data_peserta[$index_satker]['id_zonasi_satker']=$list_satker['IdZonaSatker'];
                $index_jabatan=0;
                foreach($get_jabatan_peserta as $list_jabatan_peserta){
                    $variable=str_replace(' ','_', strtolower($list_jabatan_peserta['jabatan']));
                    ${"pointer_{$variable}"}=0;
                    $$variable=null;
                    ${"index_{$variable}"}=0;
                    ${"counter_{$variable}"}=0;
                    // $data_peserta[$index_satker]['jabatan_peserta'][$index_jabatan]=$variable;
                    foreach($getPeserta as $list_peserta){
                        if((int)$list_peserta['id_kelompok_jabatan'] === (int)$list_jabatan_peserta['id_kelompok_jabatan'] && $list_satker['IdZonaSatker'] === $list_peserta['IdZonaSatker']){
                            $include="true";
                            if($variable === "juru_sita" && (int)$list_satker['IdSatkerBanding'] === (int)$list_satker['IdSatker']){
                                $include="false";
                            }
                            if($include === "true"){
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['nama']=$list_peserta['nama_pegawai'];
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_pegawai']=$list_peserta['IdPegawai'];
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_pegawai_observee']=$list_peserta['IdObservee'];
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_kelompok_jabatan']=$list_peserta['id_kelompok_jabatan'];
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_zona_satker']=$list_peserta['IdZonaSatker'];
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['is_plt']="false";
                                $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['jlh_menilai']=0;
                                ${"index_{$variable}"}+=1;
                            }
                            // shuffle($$variable);
                        }
                    }
                    $variable_jabatan_peserta_arr[$index_satker][]=str_replace(' ','_', strtolower($list_jabatan_peserta['jabatan']));
                    $id_jabatan_peserta_arr[$index_satker][]=$list_jabatan_peserta['id'];
                    $nama_jabatan_arr[$index_satker][]=$list_jabatan_peserta['jabatan'];
                    $index_jabatan++;
                }

                //check apakah ketua dan wakil tidak ada
                if(!isset($data_peserta[$index_satker]['ketua_pengadilan']) && !isset($data_peserta[$index_satker]['wakil_ketua_pengadilan'])){
                    $satker_pimpinan_kosong[]=$list_satker['IdSatker'];
                    $data_peserta[$index_satker]["plt_ketua"][0]['nama']="plt_ketua_pengadilan";
                    $data_peserta[$index_satker]["plt_ketua"][0]['id_pegawai']=0;
                    $data_peserta[$index_satker]["plt_ketua"][0]['id_pegawai_observee']=0;
                    $data_peserta[$index_satker]["plt_ketua"][0]['id_kelompok_jabatan']=15;//id_kelompok_jabatan ketua
                    $data_peserta[$index_satker]["plt_ketua"][0]['id_zona_satker']=$list_satker['IdZonaSatker'];
                    $data_peserta[$index_satker]["plt_ketua"][0]['is_plt']="true";
                    $data_peserta[$index_satker]["plt_ketua"][0]['jlh_menilai']=0;
                    $pointer_plt_ketua=0;
                    $variable_jabatan_peserta_arr[$index_satker][]="plt_ketua_pengadilan";
                    $id_jabatan_peserta_arr[$index_satker][]=1;
                    $nama_jabatan_arr[$index_satker][]="Ketua Pengadilan";
                }

                //kalau wakil ga ada, tapi ketua ada
                if(!isset($data_peserta[$index_satker]['wakil_ketua_pengadilan']) && isset($data_peserta[$index_satker]['ketua_pengadilan'])){
                    // echo "ga ada wakil ada ketua";
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['nama']=$data_peserta[$index_satker]["ketua_pengadilan"][0]['nama'];
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_pegawai']=$data_peserta[$index_satker]["ketua_pengadilan"][0]['id_pegawai'];
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_pegawai_observee']=$data_peserta[$index_satker]["ketua_pengadilan"][0]['id_pegawai_observee'];
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_kelompok_jabatan']=$data_peserta[$index_satker]["ketua_pengadilan"][0]['id_kelompok_jabatan'];
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_zona_satker']=$list_satker['IdZonaSatker'];
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['is_plt']="true";
                    $data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['jlh_menilai']=0;
                    $pointer_wakil_ketua_pengadilan=0;
                    $variable_jabatan_peserta_arr[$index_satker][]="wakil_ketua_pengadilan";
                    $id_jabatan_peserta_arr[$index_satker][]=4;
                    $nama_jabatan_arr[$index_satker][]="Wakil Ketua Pengadilan";
                }

                 //kalau ketua ga ada, tapi wakil ada
                if(isset($data_peserta[$index_satker]['wakil_ketua_pengadilan']) && !isset($data_peserta[$index_satker]['ketua_pengadilan'])){
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['nama']=$data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['nama'];
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['id_pegawai']=$data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_pegawai'];
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['id_pegawai_observee']=$data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_pegawai_observee'];
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['id_kelompok_jabatan']=$data_peserta[$index_satker]["wakil_ketua_pengadilan"][0]['id_kelompok_jabatan'];
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['id_zona_satker']=$list_satker['IdZonaSatker'];
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['is_plt']="true";
                    $data_peserta[$index_satker]["ketua_pengadilan"][0]['jlh_menilai']=0;
                    $pointer_ketua_pengadilan=0;
                    $variable_jabatan_peserta_arr[$index_satker][]="ketua_pengadilan";
                    $id_jabatan_peserta_arr[$index_satker][]=1;
                    $nama_jabatan_arr[$index_satker][]="Ketua Pengadilan";
                }

                //kalau jurusita ga ada, buat ke panitera
                // echo (int)$list_satker['IdSatkerBanding'] ."!==". (int)$list_satker['IdSatker'];
                if(!isset($data_peserta[$index_satker]['juru_sita']) && (int)$list_satker['IdSatkerBanding'] !== (int)$list_satker['IdSatker']){
                    $data_peserta[$index_satker]["juru_sita"][0]['nama']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['nama'] : 'plt_panitera';
                    $data_peserta[$index_satker]["juru_sita"][0]['id_pegawai']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['id_pegawai'] : 0;
                    $data_peserta[$index_satker]["juru_sita"][0]['id_pegawai_observee']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['id_pegawai_observee'] : 0;
                    $data_peserta[$index_satker]["juru_sita"][0]['id_kelompok_jabatan']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['id_kelompok_jabatan'] : 16;
                    $data_peserta[$index_satker]["juru_sita"][0]['id_zona_satker']=$list_satker['IdZonaSatker'];
                    $data_peserta[$index_satker]["juru_sita"][0]['is_plt']="true";
                    $data_peserta[$index_satker]["juru_sita"][0]['jlh_menilai']=0;
                    $pointer_juru_sita=0;
                    $variable_jabatan_peserta_arr[$index_satker][]="juru_sita";
                    $id_jabatan_peserta_arr[$index_satker][]=1;
                    $nama_jabatan_arr[$index_satker][]="Juru Sita";
                }

                //kalaau panitera pengganti tidak ada
                if(!isset($data_peserta[$index_satker]['panitera_pengganti'])){
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['nama']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['nama'] : 'plt_panitera';
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['id_pegawai']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['id_pegawai'] : 0;
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['id_pegawai_observee']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['id_pegawai_observee'] : 0;
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['id_kelompok_jabatan']=isset($data_peserta[$index_satker]["panitera"]) ? $data_peserta[$index_satker]["panitera"][0]['id_kelompok_jabatan'] : 16;
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['id_zona_satker']=$list_satker['IdZonaSatker'];
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['is_plt']="true";
                    $data_peserta[$index_satker]["panitera_pengganti"][0]['jlh_menilai']=0;
                    $pointer_juru_sita=0;
                    $variable_jabatan_peserta_arr[$index_satker][]="panitera_pengganti";
                    $id_jabatan_peserta_arr[$index_satker][]=1;
                    $nama_jabatan_arr[$index_satker][]="Panitera Pengganti";
                }

                //check total panmud = data peserta panmud
                $jlh_panmud=0;
                if(isset($data_peserta[$index_satker]['panitera_muda'])){
                    $jlh_panmud=count($data_peserta[$index_satker]['panitera_muda']);
                }
                // echo $list_satker['jumlah_panmud']." : ".$jlh_panmud;
                if((int)$list_satker['jumlah_panmud'] !== (int)$jlh_panmud){
                    $selisih=(int)$list_satker['jumlah_panmud'] - (int)$jlh_panmud;
                    // echo "selisih".$list_satker['jumlah_panmud']."-".$jlh_panmud;
                    for($p=0;$p<$selisih;$p++){
                        $variable="panitera_muda";
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['nama']='plt_panitera_muda';
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_pegawai']=0;
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_pegawai_observee']=0;
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_kelompok_jabatan']=31;
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['id_zona_satker']=$list_peserta['IdZonaSatker'];
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['is_plt']="true";
                        $data_peserta[$index_satker][$variable][${"index_{$variable}"}]['jlh_menilai']=0;
                        ${"index_{$variable}"}+=1;
                    }
                }
                $index_satker++;
            }
            // var_dump($data_peserta[1]);die();
            $data=[];
            $data_kosong=[];
            $jumlah_satker=$index_satker-1;
            for($s=0;$s<=$jumlah_satker;$s++){
                // echo "<b>".$satker[$s]."</b><br />";
                $jlh_jabatan_peserta=count($variable_jabatan_peserta_arr[$s]);
                
                for($x=0;$x<$jlh_jabatan_peserta;$x++){   
                    $variable_jabatan_peserta=$variable_jabatan_peserta_arr[$s][$x];
                    $id_jabatan_peserta=$id_jabatan_peserta_arr[$s][$x];
                    $jlh_pegawai_perjabatan=0;
                    if(isset($data_peserta[$s][$variable_jabatan_peserta])){
                        $jlh_pegawai_perjabatan=count($data_peserta[$s][$variable_jabatan_peserta]);
                    }
                    // $jlh_pegawai_perjabatan=count($$variable_jabatan_peserta);
                    $get_periode=Tref_zonasi::where('IdZona', $id_zonasi)->first();
                    $id_periode=$get_periode['IdTahunPenilaian'];
                    $get_mapping=Tref_mapping_jabatan::join('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'tref_mapping_jabatan.id_jabatan_penilai')
                                        ->join('trans_mapping_jabatan_periode as tmjp', function($join) use($id_periode){
                                            $join->on('tmjp.id_mapping_jabatan', '=', 'tref_mapping_jabatan.id')
                                                ->where('tmjp.id_periode', $id_periode);
                                        })
                                        ->where('id_jabatan_peserta', $id_jabatan_peserta)
                                        ->where('tref_mapping_jabatan.active', true)
                                        ->select('tref_mapping_jabatan.*', 'tjp.jabatan as jabatan_penilai')
                                        ->get();
                    $id_kelompok_jabatan_peserta_before=null;
                    for($a=0;$a<$jlh_pegawai_perjabatan;$a++){
                        // echo $pointer_wakil_ketua_pengadilan;
                        // echo "<b>".$$variable_jabatan_peserta[$a]['nama']." : "."</b>";
                        if($data_peserta[$s][$variable_jabatan_peserta][$a]['is_plt'] === "false"){
                            // echo "<b>".$data_peserta[$s][$variable_jabatan_peserta][$a]['nama']." : </b>";

                            if($variable_jabatan_peserta === "ketua_pengadilan" && $data_peserta[$s][$variable_jabatan_peserta][$a]['is_plt'] === "false" && $is_pt[$s] === "false"){
                                $get_kpt=$this->getKPT($id_zonasi_satker[$s]);
                                $data[]=[
                                        'id_zonasi'=>$id_zonasi,
                                        'id_zona_satker'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'],
                                        'id_pegawai_peserta'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_pegawai_observee'],
                                        'id_pegawai_penilai'=>$get_kpt['id_pegawai_kpt'],
                                        // 'id_jabatan_plt'=>$get_kpt['is_plt'] === "true" ?  1 : null
                                        'id_jabatan_plt'=>$get_kpt['is_plt'] === "true" ?  1 : null
                                    ];
                            }

                            $jlh_mapping=$get_mapping->count();
                            foreach($get_mapping as $mapping){
                                $variable_penilai=str_replace(' ','_', strtolower($mapping['jabatan_penilai']));
                                ${"counter_{$variable_penilai}"}+=1;
                                if(isset($data_peserta[$s][$variable_penilai])){
                                    $jlh_penilai=count($data_peserta[$s][$variable_penilai]); 
                                }else{
                                    $jlh_penilai=0;
                                    if(($variable_penilai === "ketua_pengadilan" || $variable_penilai === "wakil_ketua_pengadilan") && in_array($id_satker[$s], $satker_pimpinan_kosong)){
                                        $new_var="plt_ketua";
                                        $variable_penilai=$new_var;
                                        $jlh_penilai=1;
                                    }
                                }
                                if($jlh_penilai > 2){
                                    ${"batas_{$variable_penilai}"}=ceil($mapping['threshold']*$jlh_penilai / 100);
                                }else if($jlh_penilai === 2){
                                    ${"batas_{$variable_penilai}"}=1;
                                }else if($jlh_penilai === 1){
                                    ${"batas_{$variable_penilai}"}=1;
                                }
                                // echo  $variable_penilai." ".$jlh_penilai.", ";
                                if($jlh_penilai > 0){
                                    for($c=0;$c<${"batas_{$variable_penilai}"};$c++){
                                        // echo ${"pointer_{$variable_penilai}"};
                                        if(${"pointer_{$variable_penilai}"} > $jlh_penilai -1){
                                            ${"pointer_{$variable_penilai}"}=0;
                                        }
                                        //check peserta penilai jangan sampai menilai dirinya sendiri
                                        // echo  $data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['is_plt'].", ";
                                        if($data_peserta[$s][$variable_jabatan_peserta][$a]['id_pegawai'] === $data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['id_pegawai']){
                                            // echo "penilai dan dinilai sama: ".$$variable_jabatan_peserta[$a]['nama']." : ".$$variable_penilai[${"pointer_{$variable_penilai}"}]['nama'];
                                            if(${"pointer_{$variable_penilai}"} > $jlh_penilai -1){
                                                // echo "set ".$variable_penilai." ke 0, ";
                                                ${"pointer_{$variable_penilai}"}=0;
                                            }else{
                                                if($data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['is_plt'] === "false"){
                                                    ${"pointer_{$variable_penilai}"}+=1;
                                                }
                                            }
                                        }
                                        // echo $variable_penilai;die();
                                        // echo $data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['is_plt'].", ";
                                        // Log::error($s.", ".$variable_penilai." ".${"pointer_{$variable_penilai}"}." ".$variable_jabatan_peserta);
                                        echo "data_peserta_".$s."_".$variable_penilai."_"."pointer_".${"pointer_{$variable_penilai}"};
                                        if($data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['is_plt'] === 
                                        "true" && (int)$data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['id_pegawai'] === 0){
                                            $id_pegawai_penilai=null; 
                                            if(!in_array($mapping['id_jabatan_penilai']."-".$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'], $id_jabatan_kosong)){
                                                if(($is_pt[$s] === "true" && (int)$mapping['id_jabatan_penilai'] !== 2) || ($is_pt[$s] === "false" && (int)$mapping['id_jabatan_penilai'] >= 1)){
                                                    $data_kosong[]=[
                                                        'id_zonasi'=>$id_zonasi,
                                                        'id_zonasi_satker'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'],
                                                        'id_jabatan_kosong'=>$mapping['id_jabatan_penilai'],
                                                        'id_observee'=>null,
                                                        'created_at'=> date('Y-m-d H:i:s')
                                                    ];
                                                    $id_jabatan_kosong[]=$mapping['id_jabatan_penilai']."-".$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'];
                                                }
                                            }
                                        }else{
                                            $id_pegawai_penilai=$data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]["id_pegawai_observee"];
                                        }

                                        $data[]=[
                                            'id_zonasi'=>$id_zonasi,
                                            'id_zona_satker'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'],
                                            'id_pegawai_peserta'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_pegawai_observee'],
                                            'id_pegawai_penilai'=>$id_pegawai_penilai,
                                            'id_jabatan_plt'=>$data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['is_plt'] === "true" ?  $mapping["id_jabatan_penilai"] : null
                                        ];
                                        // echo $data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['nama'].", "; 
                                        $data_peserta[$s][$variable_penilai][${"pointer_{$variable_penilai}"}]['jlh_menilai']+=1;
                                        ${"pointer_{$variable_penilai}"}++;
                                    }
                                }else{
                                    if(!in_array($mapping['id_jabatan_penilai']."-".$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'], $id_jabatan_kosong)){
                                        if(($is_pt[$s] === "true" && (int)$mapping['id_jabatan_penilai'] !== 2) || ($is_pt[$s] === "false" && (int)$mapping['id_jabatan_penilai'] >= 1)){
                                            $data_kosong[]=[
                                                'id_zonasi'=>$id_zonasi,
                                                'id_zonasi_satker'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'],
                                                'id_jabatan_kosong'=>$mapping['id_jabatan_penilai'],
                                                'created_at'=>date('Y-m-d H:i:s'),
                                                'id_observee'=>null
                                            ];
                                            $id_jabatan_kosong[]=$mapping['id_jabatan_penilai']."-".$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'];

                                            $data[]=[
                                                'id_zonasi'=>$id_zonasi,
                                                'id_zona_satker'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_zona_satker'],
                                                'id_pegawai_peserta'=>$data_peserta[$s][$variable_jabatan_peserta][$a]['id_pegawai_observee'],
                                                'id_pegawai_penilai'=>null,
                                                'id_jabatan_plt'=>$mapping['id_jabatan_penilai']
                                            ];
                                        }
                                    }
                                    // echo "Tidak ada ".$variable_penilai.", ";
                                }
                            }
                            // echo "<br />";
                            // echo $variable_jabatan_peserta." - ";
                            $id_kelompok_jabatan_peserta_before=$data_peserta[$s][$variable_jabatan_peserta][$a]['id_kelompok_jabatan'];
                            if($a < $jlh_pegawai_perjabatan-1 && $jlh_mapping > 0){
                                //jika jabatan yang sebelumnya sama dengan jabatan selanjutnya
                                if((int)$id_kelompok_jabatan_peserta_before === (int)$data_peserta[$s][$variable_jabatan_peserta][$a+1]['id_kelompok_jabatan']){
                                    foreach($get_jabatan_peserta as $list_jabatan_peserta){
                                        $variable=str_replace(' ','_', strtolower($list_jabatan_peserta['jabatan']));
                                        if((isset($data_peserta[$s][$variable]) && count($data_peserta[$s][$variable]) === 1 || !isset($data_peserta[$s][$variable]))){
                                            // echo "variable ini 1 orang: ".$variable;
                                            ${"pointer_{$variable}"}=0;
                                        }else{
                                            
                                            if($variable ===  $variable_jabatan_peserta){
                                                ${"pointer_{$variable}"}=$a+2;
                                            }else{
                                                if(${"pointer_{$variable}"} > 0){
                                                    if($a - (int)${"batas_$variable"} < 0){
                                                        // ${"pointer_{$variable}"}=$a+1;
                                                    }else if($a - (int)${"batas_$variable"} === 0){
                                                        // ${"pointer_{$variable}"} = 0;
                                                    }else{
                                                        // echo "kurang ".${"batas_$variable"};
                                                        ${"pointer_{$variable}"}=$a - ${"batas_$variable"}; 
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // ${"pointer_{$variable}"}=0;
                                    }
                                    // echo "pointer_panitera_pengganti : ".$pointer_panitera_pengganti.", ";
                                }else{
                                    foreach($get_jabatan_peserta as $list_jabatan_peserta){
                                        $variable=str_replace(' ','_', strtolower($list_jabatan_peserta['jabatan']));
                                        // if(count($data_peserta[$s][$variable]) === 1){
                                        //     // echo $variable.", ";
                                        //     ${"pointer_{$variable}"}=0;
                                        // }
                                        if((isset($data_peserta[$s][$variable]) && count($data_peserta[$s][$variable])) === 1 || !isset($data_peserta[$s][$variable])){
                                            ${"pointer_{$variable}"}=0;
                                        }
                                    }
                                }
                            }else{
                                foreach($get_jabatan_peserta as $list_jabatan_peserta){
                                    $variable=str_replace(' ','_', strtolower($list_jabatan_peserta['jabatan']));
                                    if((isset($data_peserta[$s][$variable]) && count($data_peserta[$s][$variable])) === 1 || !isset($data_peserta[$s][$variable])){
                                        ${"pointer_{$variable}"}=0;
                                    }
                                }
                            }
                        }
                    }
                }
                Trans_observee::where('IdZonaSatker', $id_zonasi_satker[$s])->update(['entry_job'=>true]);
                $get_zonasi_satker=Zonasi_satker::where('IdZonaSatker', $id_zonasi_satker[$s])->first();
                $get_zonasi_satker->entry_job=true;
                $get_zonasi_satker->update();
            }
            // print("<pre>".print_r($data, true)."</pre>");die();
            // var_dump($data_kosong);
            $total_batch=ceil(count($data)/1000);
            $category="jobs_peserta";
            $current_job=Jobs::where('queue', 'insert_data_peserta')->count();
            $get_log=Log_msg::where('data_id', $id_zonasi)->where('category', 'jobs_peserta')->orderBy('id', 'desc')->first();
            $total_data=0;
            if(!is_null($get_log)){
                $msg_log=$get_log['msg'];
                $explode_msg=explode(": ", $msg_log);
                $total_data=(int)$explode_msg[1];
            }

            $msg="Menjalankan ".$total_batch+$current_job." Jobs Insert Peserta. Total data: ".count($data)+$total_data;
            try{
                DB::beginTransaction();
                     $this->updateProsesZonasi($id_zonasi, 2);
                     if(count($data_kosong) > 0){
                        DB::table('trans_jabatan_kosong')->insert($data_kosong);
                     }
                     $this->saveLog($id_zonasi, $category, $msg, "prepare");
                DB::commit();
                $status=true;
                $chunks=array_chunk($data, 500);
                foreach($chunks as $chunk){
                    dispatch(new InsertDataPesertaZonasi($chunk, $id_zonasi, $total_batch+$current_job))->onQueue("insert_data_peserta_".$id_zonasi);
                }
                $msg="Silahkan Jalankan Antrian Data Peserta";
            }catch(\Exception $e){
                DB::rollBack();
                $msg=$e->getMessage().":".$e->getLine() ;
            }
            
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getKPT($id_zonasi_satker){
            $get_zonasi=Zonasi_satker::where('IdZonaSatker', $id_zonasi_satker)
                                ->first();
            $id_satker_banding=$get_zonasi['IdSatkerBanding'];
            $id_zona=$get_zonasi['IdZona'];
            $get_zonasi_banding=Zonasi_satker::where('IdZona', $id_zona)
                                    ->where('IdSatker', $id_satker_banding)
                                    ->first();
            if(is_null($get_zonasi_banding)){
                $get_kpt=DB::select("CALL SPGetKPT('$id_satker_banding')");
                $jumlah=count($get_kpt);
                if($jumlah > 0 && $jumlah <= 2){
                    $x=0;
                    $pegawai_sikep=[];
                    $is_plt="true";
                    foreach($get_kpt as $list_kpt){
                        $data[$x]['id_pegawai']=$list_kpt->IdPegawai;
                        $data[$x]['nama_pegawai']=$list_kpt->NamaLengkap;
                        $data[$x]['nip']=$list_kpt->NIPBaru;
                        $data[$x]['status_pegawai']=$list_kpt->StatusPegawai;
                        $data[$x]['no_hp']=$list_kpt->NomorHandphone;
                        $data[$x]['foto_pegawai']=$list_kpt->FotoPegawai;
                        
                        $data_observee[$x]['IdPegawai']=$list_kpt->IdPegawai;
                        $data_observee[$x]['NIPBaru']=$list_kpt->NIPBaru;
                        $data_observee[$x]['id_kelompok_jabatan']=$list_kpt->IdKelompokJabatan;
                        $data_observee[$x]['IdNamaJabatan']=$list_kpt->IdNamaJabatan;
                        $data_observee[$x]['NamaJabatan']=$list_kpt->NamaJabatan;
                        $data_observee[$x]['IdZonaSatker']=$id_zonasi_satker;
                        $data_observee[$x]['endpoint']=Hashids::encode($list_kpt->IdPegawai)."-".Hashids::encode($list_kpt->IdNamaJabatan)."-".Hashids::encode($id_zonasi_satker);
                        $data_observee[$x]['diinput_tgl']=date('Y-m-d H:i:s');
                        $data_observee[$x]['diinput_oleh']="system";
                        $pegawai_sikep[]=$list_kpt->IdPegawai;
                        
                        if($list_kpt->IdKelompokJabatan === 15){
                            $is_plt="false";
                            break;
                        }
                    }
                    
                    $get_pegawai=Tref_pegawai::all();
                    $pegawai_existed=[];
                    foreach($get_pegawai as $list_pegawai){
                        $pegawai_existed[]=$list_pegawai['id_pegawai'];
                    }

                    $jlh_pegawai_sikep=count($pegawai_sikep);
                    $lookup=array_flip($pegawai_existed);
                    $data_insert=[];
                    for($x=0;$x<$jlh_pegawai_sikep;$x++){
                        if(!isset($lookup[$pegawai_sikep[$x]])){
                            $data_insert[]=$data[$x];
                        }
                    }

                    DB::table('tref_pegawai')->insert($data_insert);
                    
                    $get_observee=Trans_observee::where('IdZonaSatker', $data_observee[0]['IdZonaSatker'])
                                        ->where('IdPegawai', $data_observee[0]['IdPegawai'])
                                        ->first();
                    if(is_null($get_observee)){
                        $trans_observee=new Trans_observee;
                        $trans_observee->IdPegawai=$data_observee[0]['IdPegawai'];
                        $trans_observee->NIPBaru=$data_observee[0]['NIPBaru'];
                        $trans_observee->id_kelompok_jabatan=$data_observee[0]['id_kelompok_jabatan'];
                        $trans_observee->IdNamaJabatan=$data_observee[0]['IdNamaJabatan'];
                        $trans_observee->NamaJabatan=$data_observee[0]['NamaJabatan'];
                        $trans_observee->IdZonaSatker=$data_observee[0]['IdZonaSatker'];
                        $trans_observee->diinput_tgl=date('Y-m-d H:i:s');
                        $trans_observee->endpoint=$data_observee[0]['endpoint'];
                        $trans_observee->diinput_oleh="system";
                        $trans_observee->save();
                        $observee_id=$trans_observee->IdObservee;

                    }else{
                        $observee_id=$get_observee['IdObservee'];
                    }
                    $kpt['id_pegawai_kpt']=$observee_id;
                    $kpt['is_plt']=$is_plt;
                }else{
                    $kpt['id_pegawai_kpt']=null;
                    $kpt['is_plt']="true";
                }
            }else{
                $id_zonasi_satker_banding=$get_zonasi_banding['IdZonaSatker'];
                $get_pimpinan_pt=Trans_observee::where('IdZonaSatker', $id_zonasi_satker_banding)
                                    ->where(function($w){
                                        $w->where('id_kelompok_jabatan', 15)
                                        ->orWhere('id_kelompok_jabatan', 27);
                                    })
                                    ->orderBy('id_kelompok_jabatan', 'asc')
                                    ->get();
                if($get_pimpinan_pt->count() > 0){
                    foreach($get_pimpinan_pt as $pimpinan_pt){
                        $kpt['is_plt']="true";
                        $kpt['id_pegawai_kpt']=$pimpinan_pt['IdObservee'];
                        if((int)$pimpinan_pt['id_kelompok_jabatan'] === 15){
                            $kpt['is_plt']="false";
                            break;
                        }
                    }
                }else{
                    $kpt['id_pegawai_kpt']=null;
                    $kpt['is_plt']="true";
                }
            }
            return $kpt;
        }
        
        public function saveLog($data_id, $category, $msg, $status){
            $log=new Log_msg;
            $log->data_id=$data_id;
            $log->category=$category;
            $log->msg=$msg;
            $log->status=$status;
            $log->save();
        }

        public function updateProsesZonasi($zonasi_id, $proses_id){
            $get_data=Tref_zonasi::where('IdZona', $zonasi_id)->first();
            $get_data->proses_id=$proses_id;
            $get_data->diperbarui_oleh="batch_system";
            $get_data->diperbarui_tgl=date('Y-m-d H:i:s');
            $get_data->update();
        }

        public function countProgress($id_zonasi){
            $status=false;
            $get_log_prepare=Log_msg::where('data_id', $id_zonasi)
                                ->where('activity', 'current')
                                ->get();
            $jlh_log=$get_log_prepare->count();
            if($jlh_log > 0){
                $status="progress";
                $msg="";
                foreach($get_log_prepare as $list_log){
                    $msg.=$list_log['msg']."\r";
                    if($list_log['category'] === "jobs_peserta" && $list_log['status'] === "failed"){
                        $status="failed";
                    }else if($list_log['category'] === "jobs_peserta" && $list_log['status'] === "finished"){
                        $status="done";
                    }
                }
            }else{
                $msg="Data zonasi tidak ada";
            }
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getJabatanKosong($page, $id_zonasi){
            if($page < 1){
                $page=1;
            }
            $limit=10;
            $total=Trans_jabatan_kosong::where('id_zonasi', $id_zonasi)->count();
            $jumlah_halaman=ceil($total / $limit);
            $skip=$page * $limit - $limit;
            $data=[];
            // $data_kosong=[];
            $status=false;
            $msg="";
            $get_jabatan_kosong=Trans_jabatan_kosong::join('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'trans_jabatan_kosong.id_jabatan_kosong')
                                        ->join('trans_zonasi_satker as tzs', 'tzs.IdZonaSatker', '=', 'trans_jabatan_kosong.id_zonasi_satker')
                                        ->join('v_satker as vs', 'vs.IdSatker', '=', 'tzs.IdSatker')
                                        ->select('vs.NamaSatker', 'tjp.jabatan', 'trans_jabatan_kosong.id_observee', 'trans_jabatan_kosong.status')
                                        ->skip($skip)->take($limit)
                                        ->where('trans_jabatan_kosong.id_zonasi', $id_zonasi)
                                        ->get();
            $jumlah=$get_jabatan_kosong->count();
            if($jumlah > 0){
                $status=true;
                $x=0;
                foreach($get_jabatan_kosong as $list_jabatan){
                    $data[$x]['nama_satker']=$list_jabatan['NamaSatker'];
                    $data[$x]['jabatan']=$list_jabatan['jabatan'];
                    $data[$x]['filled']=(int)$list_jabatan['status'] === 1 ? "Y" : "N";
                    $x++;
                }
            }else{
                $msg="Data tidak ada ".$id_zonasi;
            }
            return [
                'status'=>$status,
                'msg'=>$msg,
                'page'=>$page,
                'total'=>$total,
                'jumlah_halaman'=>$jumlah_halaman,
                'no'=>$skip+1,
                'data'=>$data,
            ];
        }

        public function checkEntryJobTransZonasiSatker($id_zonasi){
            $status=false;
            $jlh_entry_job_true=0;
            $jlh_entry_job_false=0;
            $msg="";
            $get_data=Zonasi_satker::where('IdZona', $id_zonasi)->get();
            $total=$get_data->count();
            if($total > 0){
                $status=true;
                $jlh_entry_job_true=0;
                $jlh_entry_job_false=0;
                foreach($get_data as $list_data){
                    if($list_data['entry_job'] === 1){
                        $jlh_entry_job_true+=1;
                    }else{
                        $jlh_entry_job_false+=1;
                    }
                }
            }else{
                $msg="Data Zonasi Satker tidak ditemukan";
            }
            return [
                'status'=>$status,
                'entry_job_true'=>$jlh_entry_job_true,
                'entry_job_false'=>$jlh_entry_job_false,
                'msg'=>$msg
            ];
        }
        
        public function getPesertaZonasiSatker($id_zonasi_satker){
            $status=false;
            $data=[];
            $get_data=Zonasi_satker::where('IdZonaSatker', $id_zonasi_satker)->first();
            if(!is_null($get_data)){
                $status=true;
                $msg="Data reserved";
                $get_peserta_zonasi=Trans_peserta_zonasi::join('trans_observee as to1', 'to1.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_peserta')
                                    ->join('tref_pegawai as tp1', 'tp1.id_pegawai', '=', 'to1.IdPegawai')
                                    ->leftJoin('trans_observee as to2', 'to2.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_penilai')
                                    ->leftJoin('tref_pegawai as tp2', 'tp2.id_pegawai', '=', 'to2.IdPegawai')
                                    ->leftJoin('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'trans_peserta_zonasi.id_jabatan_plt')
                                    ->select('trans_peserta_zonasi.*', 'tp1.nama_pegawai as nama_peserta', 'tp2.nama_pegawai as nama_penilai', 'tjp.jabatan as jabatan_plt', 'to1.NamaJabatan as nama_jabatan_peserta', 'to2.NamaJabatan as nama_jabatan_penilai')
                                    ->orderBy('trans_peserta_zonasi.id_pegawai_peserta', 'asc')
                                    ->where('trans_peserta_zonasi.id_zona_satker', $id_zonasi_satker)
                                    ->get();
                $id_pegawai_peserta_before=0;
                $data=[];
                $x=0;
                $index_peserta=0;
                foreach($get_peserta_zonasi as $list_peserta){
                    if($id_pegawai_peserta_before !== (int)$list_peserta['id_pegawai_peserta']){
                        if($index_peserta > 0){
                            $x+=1;
                        }
                        $y=0;
                        $data[$x]['nama_peserta']=$list_peserta['nama_peserta'];
                        $data[$x]['token_jabatan_peserta']=Hashids::encode($list_peserta['id']);
                        $data[$x]['jabatan_peserta']=$list_peserta['nama_jabatan_peserta'];
                    }
                    $data[$x]['penilai'][$y]['nama_penilai']=$list_peserta['nama_penilai'];
                    $data[$x]['penilai'][$y]['jabatan_penilai']=$list_peserta['nama_jabatan_penilai'];
                    $data[$x]['penilai'][$y]['is_plt']=is_null($list_peserta['id_jabatan_plt']) ? 'N' : 'Y';
                    $data[$x]['penilai'][$y]['jabatan_plt']=$list_peserta['jabatan_plt'];
                    $data[$x]['penilai'][$y]['nilai']=$list_peserta['nilai'];

                    $y++;
                    $index_peserta++;
                    $id_pegawai_peserta_before=$list_peserta['id_pegawai_peserta'];
                }
                
            }else{
                $msg="Data Zonasi Satker ini tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
        }

        public function sendNotifJabatanKosong($id_zonasi){
            $status=false;
            $get_data=Trans_jabatan_kosong::join('trans_zonasi_satker as tzs', 'tzs.IdZonaSatker', '=', 'trans_jabatan_kosong.id_zonasi_satker')
                            ->join('tref_users as tu', 'tu.IdSatker', '=', 'tzs.IdSatker')
                            ->join('tref_pegawai as tp', 'tp.id_pegawai', '=', 'tu.IdPegawai')
                            ->select('tp.no_hp', 'tp.nama_pegawai')
                            ->where('trans_jabatan_kosong.id_zonasi', $id_zonasi)
                            ->groupBy('id_zonasi_satker')
                            ->groupBy('tp.no_hp')
                            ->groupBy('tp.nama_pegawai')
                            ->get();
            $jumlah=$get_data->count();
            if($jumlah > 0){
                $sent=0;
                foreach($get_data as $list_admin){
                    $msg=getWAMsg("jabatan_kosong", "");
                    $no_hp=$list_admin['no_hp'];
                    $no_hp="081273861528";
                    $sendWa=sendWa($msg, $no_hp);
                    $status_kirim=$sendWa['status'];
                    if($status_kirim === "ok"){
                        $sent+=1;
                    }
                }
                if($sent === $jumlah){
                    $status=true;
                    $msg="Berhasil mengirimkan Pesan Jabatan Kosong. ";
                    $status_log="finished";
                }else{
                    $msg="Tidak mengirimkan pesan Jabatan Kosong. ";
                    $status_log="error";
                }
                $log=new Log_msg;
                $log->data_id=$id_zonasi;
                $log->category="send_notif";
                $log->msg=$msg." ".$sent." Pesan dari ".$jumlah;
                $log->status=$status_log;
                $log->activity="current";
                $log->save();
            }else{
                $msg="Tidak ada data jabatan kosong";
            }
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getDataZonasiSatker($id_zona){
            $id_zonasi_satker=[];
            $get_zonasi_satker=Zonasi_satker::where('IdZona', $id_zona)->get();
            if($get_zonasi_satker->count() > 0){
                foreach($get_zonasi_satker as $list_zona){
                    $id_zonasi_satker[]=$list_zona['IdZonaSatker'];
                }
            }
            return $id_zonasi_satker;
        }

        public function monitoringBadilum($id_zona, $page){
            $jumlah_halaman=0;
            $msg="";
            $data=[];
            $status=false;
            $get_zonasi_satker=$this->getDataZonasiSatker($id_zona);
            $jumlah_zonasi_satker=count($get_zonasi_satker);
            if($jumlah_zonasi_satker > 0){
                $status=true;
                $limit = 50;
                $jumlah_halaman=ceil($jumlah_zonasi_satker / $limit);
                if($page > $jumlah_halaman){
                    $page =1;
                }
                $skip=$page * $limit - $limit;
                $get_summary_penilaian=Cache::store('redis')->remember("monitoring_badilum_{$id_zona}_{$page}_{$limit}", 3600*24*360, function () use($skip, $limit) {
                     return DB::table('trans_peserta_zonasi as tpz')
                                    ->join('trans_zonasi_satker as tzs', 'tzs.IdZonaSatker', '=', 'tpz.id_zona_satker')
                                    ->join('v_satker as vs', 'vs.IdSatker', '=', 'tzs.IdSatker')
                                    ->select("NamaSatker", "id_zona_satker", DB::raw("COUNT(CASE WHEN tpz.nilai = 0 then 1 END) AS belum"), DB::raw("COUNT(CASE WHEN tpz.nilai > 0 then 1 END) AS sudah"), DB::raw("COUNT(id_zona_satker) AS total"))
                                    ->skip($skip)->take($limit)
                                    ->groupBy("id_zona_satker")
                                    ->groupBy("NamaSatker")
                                    ->get();
                });
                $get_summary_penilaian->toArray();
                $jumlah_penilaian=count($get_summary_penilaian);
                if($jumlah_penilaian > 0){
                    $x=0;
                    foreach($get_summary_penilaian as $list_penilaian){
                        $data[$x]['nama_satker']=$list_penilaian->NamaSatker;
                        $data[$x]['id_zona_satker']=Hashids::encode($list_penilaian->id_zona_satker);
                        $data[$x]['belum_nilai']=$list_penilaian->belum;
                        $data[$x]['sudah_nilai']=$list_penilaian->sudah;
                        $data[$x]['total_penilaian']=$list_penilaian->total;
                        $data[$x]['percentage']=$list_penilaian->sudah / $list_penilaian->total * 100;
                        $x++;
                    }
                }else{
                    $msg="Data Satker tidak ditemukan";
                }
            }else{
                $msg="Data Satker tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'jumlah_halaman'=>$jumlah_halaman,
                'page'=>$page,
                'data'=>$data
            ];
        }

        public function getZonasiByPeriode($id_periode){
            $get_zonasi=Cache::store('redis')->remember("zonasi_periode_{$id_periode}", 3600*24*365, function() use($id_periode){
                return Tref_zonasi::where('IdTahunPenilaian', $id_periode)
                        ->select('nama_zona', 'IdZona')    
                        ->get();
            });
            $data=[];$x=0;
            foreach($get_zonasi as $list_zonasi){
                $data[$x]['token_zonasi']=Hashids::encode($list_zonasi['IdZona']);
                $data[$x]['nama_zonasi']=$list_zonasi['nama_zona'];
                $x++;
            }

            return $data;
        }
    }

?>