<?php
    namespace App\Services;

    use App\Models\Tahapan_proses;
    use App\Models\Tahun_penilaian;
    use App\Models\Tref_zonasi;
    use Vinkla\Hashids\Facades\Hashids;
    use Illuminate\Support\Facades\DB;
    use App\Models\Trans_bobot_penilaian_periode;
    use App\Models\Trans_mapping_jabatan_periode;
use App\Models\Trans_pertanyaan_periode;
use App\Models\Tref_bobot_penilaian;
use App\Models\Tref_jawaban_bundle;
use App\Models\Tref_mapping_jabatan;
use App\Models\Tref_pertanyaan;
use App\Models\Tref_jabatan_peserta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use PDO;

    class periodeService{
        public function getListPeriode($page){
            $data=[];
            $total=Tahun_penilaian::count();
            $limit=10;
            $jumlahHalaman=ceil($total / $limit);
            // $status_periode=['Register', 'Running', 'Selesai'];
            $get_tahapan_proses=Tahapan_proses::all();
            $status_periode=[];
            foreach($get_tahapan_proses as $list_tahapan){
                $status_periode[]=$list_tahapan['proses'];
            }
            if($page < 0 || $page > $jumlahHalaman || is_null($page)){
                $page=1;
            }

            $skip=$page * $limit - $limit;

            if($total > 0){
                $get_data=Tahun_penilaian::orderBy('IdTahunPenilaian', 'desc')
                                    ->skip($skip)->take($limit)
                                    ->get();
                $x=0;
                foreach($get_data as $list_data){
                    $data[$x]['id']=Hashids::encode($list_data['IdTahunPenilaian']);
                    $data[$x]['tahun']=$list_data['tahun'];
                    $data[$x]['dasar_hukum']=$list_data['dasar_hukum'];
                    $data[$x]['keterangan']=$list_data['keterangan'];
                    $data[$x]['proses']=$status_periode[$list_data['proses_id']-1];
                    $x++;
                }
            }

            return [
                'page'=>$page,
                'total'=>$total,
                'jumlah_halaman'=>$jumlahHalaman,
                'start_number'=>$skip+1,
                'data'=>$data,
            ];
        }


        public function savePeriode($request, $path){
            $save=false;
            $check_active=Tahun_penilaian::whereRaw('proses_id <= 1')->count();
            if($check_active === 0){
                try{
                    DB::beginTransaction();
                        $get_data=Tahun_penilaian::where('tahun', $request->tahun)->count();
                        $index=$get_data+=1;            
                        $new_periode=new Tahun_penilaian;
                        $new_periode->tahun=$request->tahun;
                        $new_periode->dasar_hukum=$path;
                        $new_periode->keterangan=$request->tahun." Gelombang ".$index;
                        $new_periode->save();
                        $get_periode=Tahun_penilaian::where('tahun', $request->tahun)
                                            ->where('dasar_hukum', $path)
                                            ->where('proses_id', 1)
                                            ->first();
                        if(!is_null($get_periode)){
                            $id_periode=$get_periode['IdTahunPenilaian'];

                            $get_bobot_penilaian=Tref_bobot_penilaian::where('active', true)
                                                        ->whereRaw('tref_bobot_penilaian.bobot is not null')
                                                        ->get();
                            $get_mapping_jabatan=Tref_mapping_jabatan::where('active', true)->get();
                            $get_pertanyaan=Tref_pertanyaan::where('active', true)->get();
                            $jlh_mapping_jabatan=$get_mapping_jabatan->count();
                            $jlh_bobot_penilaian=$get_bobot_penilaian->count();
                            $jlh_pertanyaan=$get_pertanyaan->count();
                            if($jlh_bobot_penilaian > 0 && $jlh_mapping_jabatan > 0 && $jlh_pertanyaan > 0){
                                if($jlh_bobot_penilaian === $jlh_mapping_jabatan){
                                    $bobot_penilaian_periode=[];
                                    foreach($get_bobot_penilaian as $list_bobot_penilaian){
                                        $bobot_penilaian_periode[]=[
                                            'id_periode'=>$id_periode,
                                            'id_bobot_penilaian'=>$list_bobot_penilaian['id'],
                                            'bobot'=>$list_bobot_penilaian['bobot'],
                                        ];
                                    }
                                    
                                    $mapping_jabatan_periode=[];
                                    foreach($get_mapping_jabatan as $list_mapping_jabatan){
                                        $mapping_jabatan_periode[]=[
                                            'id_periode'=>$id_periode,
                                            'id_mapping_jabatan'=>$list_mapping_jabatan['id'],
                                        ];
                                    }

                                    $pertanyaan_periode=[];
                                    foreach($get_pertanyaan as $list_pertanyaan){
                                        $pertanyaan_periode[]=[
                                            'id_periode'=>$id_periode,
                                            'id_variable'=>$list_pertanyaan['id_variable'],
                                            'pertanyaan'=>$list_pertanyaan['pertanyaan'],
                                            'id_pertanyaan'=>$list_pertanyaan['id'],
                                            'bundle_code_jawaban'=>$list_pertanyaan['bundle_code_jawaban'],
                                            'bobot'=>$list_pertanyaan['bobot']
                                        ];
                                    }
                                    
                                    DB::table('trans_mapping_jabatan_periode')->insert($mapping_jabatan_periode);
                                    DB::table('trans_bobot_penilaian_periode')->insert($bobot_penilaian_periode);
                                    DB::table('trans_pertanyaan_periode')->insert($pertanyaan_periode);
                                    Cache::store('redis')->forget('all_periode');

                                    //Digunakan di zonasiService@getDataSatkerLengkap: Ambil
                                    Cache::store('redis')->forget("satker_pt");
                                    $keys=Redis::keys('laravel-cache-satker_pn_*');
                                    if(!empty($keys)){
                                        Redis::del($keys);
                                    }
                                }else{
                                    $msg="Masih ada bobot penilaian yang harus diisi";
                                    throw new \Exception($msg);
                                }
                            }else{
                                $msg="Tidak bisa menambahkan Periode. Silahkan melengkapi bobot Penilaian, Mapping Jabatan atau Pertanyaan Terlebih dahulu";
                                throw new \Exception($msg);
                            }
                        }else{
                            $msg="Data Periode Tidak ditemukan";
                            throw new \Exception($msg);
                        }
                    DB::commit();
                    $msg="Berhasil menyimpan data";
                    $save=true;
                }catch(\Exception $e){
                    DB::rollback();
                    $msg=$e->getMessage();
                }
            }else{
                $msg="Masih ada Periode yang aktif. Silahkan selesaikan Periode yang aktif";
            }

            return [
                'status'=>$save,
                'msg'=>$msg
            ];
        }

        public function getPeriodeById($id){
            $status=false;
            $data=[];
            $msg="";
            $get_data=Tahun_penilaian::where('IdTahunPenilaian', $id)->first();
            if(!is_null($get_data)){
                $status_periode=['Register', 'Running', 'Selesai'];
                $status=true;
                $data['enc_id']=Hashids::encode($get_data['IdTahunPenilaian']);
                $data['tahun']=$get_data['tahun'];
                $data['keterangan']=$get_data['keterangan'];
                $data['edoc_dasar_hukum']=$get_data['dasar_hukum'];
                $data['proses']=$status_periode[$get_data['proses_id']-1];
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'data'=> $data,
                'msg'=>$msg
            ];
        }

        public function updatePeriode($request, $id, $path=null){
            $status=false;
            $get_data=Tahun_penilaian::where('IdTahunPenilaian', $id)->first();
            if(!is_null($get_data)){
                if((int)$get_data['tahun'] !== (int)$request->tahun){
                    $exists_year=Tahun_penilaian::where('tahun', $request->tahun)->count();
                    $jumlah=$exists_year+=1;
                    $get_data->keterangan=$request->tahun." Gelombang ".$jumlah;
                }
                if(!is_null($path)){
                    $get_data->dasar_hukum=$path;
                }
                $get_data->tahun=$request->tahun;
                if($get_data->update()){
                    $status=true;
                    $msg="Berhasil mengubah data";
                    Cache::store('redis')->forget('all_periode');
                }else{
                    $msg="Terjadi kesalahan sistem saat mengubah data";
                }
                
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function deletePeriode($id){
            $status=false;
            $checkZonasi=Tahun_penilaian::join('tref_zonasi', function($join){
                                        $join->on('tref_zonasi.IdTahunPenilaian', '=', 'tref_tahun_penilaian.IdTahunPenilaian')
                                            ->where('tref_zonasi.is_active', true);
                                    })
                                ->where('tref_tahun_penilaian.IdTahunPenilaian', $id)
                                ->count();
            if($checkZonasi > 0){
                $msg="Periode ini sedang digunakan pada zonasi. Silahkan menghapus atau menonaktifkan zonasi sebelumnya";
            }else{
                try{
                    DB::beginTransaction();
                        $periode=Tahun_penilaian::where('IdTahunPenilaian', $id)->first();
                        $periode->delete();
                        $deleted_bobot=Trans_bobot_penilaian_periode::where('id_periode', $id)->delete();
                        $deleted_mapping=Trans_mapping_jabatan_periode::where('id_periode', $id)->delete();
                        $deleted_pertanyaan=Trans_pertanyaan_periode::where('id_periode', $id)->delete();
                        if($deleted_bobot > 0 && $deleted_mapping > 0 && $deleted_pertanyaan > 0){
                            DB::commit();
                            $status=true;
                            $msg="Berhasil menghapus data";
                        }else{
                            $msg="Beberapa data tidak dapat dihapus. Silahkan hubungi tim Development";
                        }
                }catch(\Exception $e){
                    $msg=$e->getMessage();
                }
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function finishPeriode($id_periode){
            $finished=false;
            $get_zonasi=Tref_zonasi::where('IdTahunPenilaian', $id_periode)
                            ->whereRaw('proses_id <> 3')
                            ->first();
            if(!is_null($get_zonasi)){
                $msg="Data zonasi masih ada yang status Running";
            }else{
                $get_periode=Tahun_penilaian::where('id', $id_periode)->first();
                if(!is_null($get_periode)){
                    // $get_periode->delete();
                    $get_periode->proses_id=3;
                    if($get_periode->update()){
                        $finished=true;
                        $msg="Berhasil menghapus data";
                    }else{
                        $msg="Data tidak dapat diupdate";
                    }
                }else{
                    $msg="Data zonasi tidak ditemukan";
                }
            }

            return [
                'status'=>$finished,
                'msg'=>$msg
            ];
        }

        public function listActivePeriode(){
            $data=[];
            $get_periode=Tahun_penilaian::where('proses_id', '<>', 3)->get();
            $jumlah=$get_periode->count();
            if($jumlah > 0){
                $x=0;
                foreach($get_periode as $list_data){
                    $data[$x]['id']=Hashids::encode($list_data['IdTahunPenilaian']);
                    $data[$x]['tahun']=$list_data['tahun'];
                    $data[$x]['dasar_hukum']=$list_data['dasar_hukum'];
                    $data[$x]['keterangan']=$list_data['keterangan'];
                    $x++;
                }
            }

            return [
                'total'=>$jumlah,
                'data'=>$data
            ];
        }

        public function getBobotPenilaianPeriode($periode_id){  
            $status=false;
            $data=[];
            $msg="";
            $signature="";
            $get_bobot=Trans_bobot_penilaian_periode::join('tref_tahun_penilaian as ttp', 'ttp.IdTahunPenilaian', '=', 'trans_bobot_penilaian_periode.id_periode')
                                            ->join('tref_bobot_penilaian as bp', 'bp.id', '=', 'trans_bobot_penilaian_periode.id_bobot_penilaian')
                                            ->join('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'bp.id_jabatan_peserta')
                                            ->join('tref_jabatan_peserta as tjp2', 'tjp2.id', '=', 'bp.id_jabatan_penilai')
                                            ->select('trans_bobot_penilaian_periode.id as id_trans_bobot', 'tjp.jabatan as jabatan_peserta', 'tjp2.jabatan as jabatan_penilai', 'bp.bobot')
                                            ->where('id_periode', $periode_id)
                                            ->get();
            $jumlah=$get_bobot->count();
            if($jumlah > 0){
                $x=0;                                                                           
                foreach($get_bobot as $list_bobot){
                    $data[$x]['token_trans_bobot']=Hashids::encode($list_bobot['id_trans_bobot']);
                    $data[$x]['jabatan_peserta']=$list_bobot['jabatan_peserta'];
                    $data[$x]['jabatan_penilai']=$list_bobot['jabatan_penilai'];
                    $data[$x]['bobot']=$list_bobot['bobot'];
                    $x++;
                }
                $secret=config('app.hmac_secret');
                $payload=json_encode(['payload'=>Hashids::encode($periode_id)]);
                $signature=hash_hmac('sha256', $payload, $secret);
                $status=true;
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data
            ];
        }

        public function removeBobotPenilaianPeriode($id_trans_bobot, $id_periode){
            $delete=false;
            $msg="";
            $get_data=Trans_bobot_penilaian_periode::where('id_periode', $id_periode)
                                ->where('id', $id_trans_bobot)
                                ->first();
            if(!is_null($get_data)){
                $get_periode=Tahun_penilaian::where('IdTahunPenilaian', $id_periode)->first();
                if(!is_null($get_periode)){
                    $proses_id=(int)$get_periode['proses_id'];
                    if($proses_id <= 3){
                        if($get_data->delete()){
                            $delete=true;
                            $msg="Berhasil menghapus data";
                        }else{
                            $msg="Terjadi kesalahan sistem saat mengubah data";
                        }
                    }else{
                        $msg="Tidak dapat menghapus data. Periode ini sudah berjalan";
                    }
                }else{
                    $msg="Data Periode tidak ada";
                }
            }else{
                $msg="Data Bobot Penilaian di Periode ini tidak ditemukan";
            }

            return [
                'status'=>$delete,
                'msg'=>$msg
            ];
        }

        public function regenerateBobotPenilaian($id_periode){
            $status=false;
            $get_periode=Tahun_penilaian::where('IdTahunPenilaian', $id_periode)->first();
            if(!is_null($get_periode)){
                $proses_periode=(int)$get_periode['proses_id'];
                if($proses_periode === 1){
                    try{
                        DB::beginTransaction();
                            Trans_bobot_penilaian_periode::where('id_periode', $id_periode)->delete();
                            $get_ref_bobot=Tref_bobot_penilaian::where('active', true)->get();
                            $data_bobot=[];
                            $total=$get_ref_bobot->count();
                            if($total > 0){
                                foreach($get_ref_bobot as $list_bobot){
                                    $data_bobot[]=[
                                        'id_periode'=>$id_periode,
                                        'id_bobot_penilaian'=>$list_bobot['id'],
                                        'bobot'=>$list_bobot['bobot']
                                    ];
                                }
                                DB::table('trans_bobot_penilaian_periode')->insert($data_bobot);
                                DB::commit();
                                $status=true;
                                $msg="Berhasil memperbaharui data Bobot periode";
                                Cache::store('redis')->forget("bobot_periode_{$id_periode}");
                            }else{
                                DB::rollBack();
                                $msg="Data Master Bobot Pertanyaan tidak ada. Silahkan diisi terlebih dahulu";
                            }
                    }catch(\Exception $e){
                        DB::rollback();
                        $msg=$e->getMessage();
                    }
                }else{
                    $msg="Tidak dapat melakukan update data Bobot Penilaian";
                }
            }else{
                $msg="Data Periode tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getPertanyaanPeriode($id_periode){
            $status=false;
            $data=[];
            $msg="";
            $signature="";
            $bundle_jawaban=Tref_jawaban_bundle::select('bundle_code', 'bundle_name')
                                        ->distinct();
            $get_data=Trans_pertanyaan_periode::join('tref_pertanyaan as tp', 'tp.id', '=', 'trans_pertanyaan_periode.id_pertanyaan')
                                    ->join('variable_pertanyaan as vp', 'vp.id', '=', 'tp.id_variable')
                                    ->joinSub($bundle_jawaban, 'b', function($join){
                                            $join->on('tp.bundle_code_jawaban', '=', 'b.bundle_code');
                                        })
                                    ->select('trans_pertanyaan_periode.id as id_pertanyaan_periode', 'vp.variable', 'tp.id_variable', 'tp.pertanyaan', 'tp.bobot', 'b.bundle_name')
                                    ->orderBy('vp.id', 'asc')
                                    ->where('trans_pertanyaan_periode.id_periode', $id_periode)
                                    ->get();
            $total=$get_data->count();
            if($total > 0){
                $status=true;
                $x=$a=0;
                $id_variable_before=null;
                foreach($get_data as $list_data){
                    if($id_variable_before !== $list_data['id_variable']){
                        $a=0;
                        if(!is_null($id_variable_before)){
                            $x++;
                        }
                        $data[$x]['variable_pertanyaan']=$list_data['variable'];
                        $data[$x]['list_pertanyaan']=[];
                    }
                    $data[$x]['list_pertanyaan'][$a]['token_trans_pertanyaan']=Hashids::encode($list_data['id_pertanyaan_periode']);
                    $data[$x]['list_pertanyaan'][$a]['pertanyaan']=$list_data['pertanyaan'];
                    $data[$x]['list_pertanyaan'][$a]['bobot']=$list_data['bobot'];
                    $data[$x]['list_pertanyaan'][$a]['bundle_jawaban']=$list_data['bundle_name'];
                    $a++;
                    $id_variable_before=$list_data['id_variable'];
                }
                $secret=config('app.hmac_secret');
                $payload=json_encode(['payload'=>Hashids::encode($id_periode)]);
                $signature=hash_hmac('sha256', $payload, $secret);
            }else{
                $msg="Data tidak ditemukan";
            }
            
            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data
            ];
        }

        public function removePertanyaanPeriode($id_trans_pertanyaan, $id_periode){
            $status=false;
            $check_periode=Tahun_penilaian::where('IdTahunPenilaian', $id_periode)
                            ->first();
            if(!is_null($check_periode)){
                $proses_id=(int)$check_periode['proses_id'];
                if($proses_id <= 3){
                    $get_trans_pertanyaan=Trans_pertanyaan_periode::where('id', $id_trans_pertanyaan)->first();
                    if(!is_null($get_trans_pertanyaan)){
                        if($get_trans_pertanyaan->delete()){
                            $status=true;
                            $msg="Berhasil menghapus Pertanyaan";
                        }else{
                            $msg="Terjadi kesalahan sistem saat menghapus data";
                        }
                    }else{
                        $msg="Data pertanyaan pada periode ini tidak ditemukan";
                    }
                }else{
                    $msg="Tidak dapat menghapus pertanyaan. Periode ini telah berjalan";
                }
            }else{
                $msg="Data Periode tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function regeneratePertanyaanPeriode($id_periode){
            $get_periode=Tahun_penilaian::where('IdTahunPenilaian', $id_periode)->first();
            if(!is_null($get_periode)){
                $proses_id=(int)$get_periode['proses_id'];
                if($proses_id <= 3){
                    try{
                        DB::beginTransaction();
                            Trans_pertanyaan_periode::where('id_periode', $id_periode)->delete();
                            $get_data_pertanyaan=Tref_pertanyaan::where('active', true)->get();
                            if($get_data_pertanyaan->count() > 0){
                                $data_pertanyaan=[];
                                foreach($get_data_pertanyaan as $list_pertanyaan){
                                    $data_pertanyaan[]=[
                                        'id_periode'=>$id_periode,
                                        'id_pertanyaan'=>$list_pertanyaan['id'],
                                    ];
                                }
                                DB::table('trans_pertanyaan_periode')->insert($data_pertanyaan);
                                DB::commit();
                                $status=true;
                                $msg="Berhasil Memperbaharui Data";
                            }else{
                                DB::rollBack();
                                $msg="Master data pertanyaan tidak ditemukan. Silahkan diisi ulang";
                            }
                        
                    }catch(\Exception $e){
                        $msg=$e->getMessage();
                        DB::rollBack();
                    }
                }else{
                    $msg="Tidak dapat melakukan update data Pertanyaan";
                }
            }else{
                $msg="Data Periode tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }
        
        public function getMappingJabatanPeriode($id_periode){
            $data=[];
            $data_jabatan=[];
            $status=false;
            $msg="";
            $signature="";
            $get_data_jabatan=Tref_jabatan_peserta::where('active', true)
                            ->get();
            $get_data=Trans_mapping_jabatan_periode::join('tref_mapping_jabatan as tmj', 'tmj.id', '=', 'trans_mapping_jabatan_periode.id_mapping_jabatan')
                                                        ->join('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'tmj.id_jabatan_peserta')
                                                        ->join('tref_jabatan_peserta as tjp2', 'tjp2.id', '=', 'tmj.id_jabatan_penilai')
                                                        ->select('trans_mapping_jabatan_periode.id as id_trans_mapping', 'tjp.jabatan as jabatan_peserta', 'tjp2.jabatan as jabatan_penilai', 'tmj.threshold', 'tmj.id_jabatan_peserta')
                                                        ->where('trans_mapping_jabatan_periode.id_periode', $id_periode)
                                                        ->orderBy('tmj.id_jabatan_peserta', 'asc')
                                                        ->get();
            $a=$b=0;

            foreach($get_data_jabatan as $list_jabatan){
                $data_jabatan[$a]['jabatan_peserta']=$list_jabatan['jabatan'];
                $data_jabatan[$a]['jabatan_penilai']=[];
                $c=0;
                foreach($get_data as $list_mapping_jabatan){
                    if((int)$list_mapping_jabatan['id_jabatan_peserta'] === (int)$list_jabatan['id']){
                        if($c > 0){
                            $b++;
                        }
                    }else{
                        $b=0;
                    }
                    $data_jabatan[$a]['jabatan_penilai'][$b]['token_trans_mapping']=Hashids::encode($list_mapping_jabatan['id_trans_mapping']);
                    $data_jabatan[$a]['jabatan_penilai'][$b]['jabatan'] = $list_mapping_jabatan['jabatan_penilai'];
                    $data_jabatan[$a]['jabatan_penilai'][$b]['threshold']=$list_mapping_jabatan['threshold'];
                    $c++;
                }
                $a++;
            }

            $jumlah=$get_data->count();
            if($jumlah > 0){
                $status=true;
                $x=$a=0;
                $id_jabatan_peserta_before=null;
                $secret=config('app.hmac_secret');
                $payload=json_encode(['payload'=>Hashids::encode($id_periode)]);
                $signature=hash_hmac('sha256', $payload, $secret);

                foreach($get_data as $list_mapping){
                    if((int)$list_mapping['id_jabatan_peserta'] !== $id_jabatan_peserta_before){
                        $a=0;
                        if(!is_null($id_jabatan_peserta_before)){
                            $x++;
                        }
                        $data[$x]['jabatan_peserta']=$list_mapping['jabatan_peserta'];
                        $data[$x]['jabatan_penilai']=[];
                    }
                    $data[$x]['jabatan_penilai'][$a]['token_trans_mapping']=Hashids::encode($list_mapping['id_trans_mapping']);
                    $data[$x]['jabatan_penilai'][$a]['jabatan']=$list_mapping['jabatan_penilai'];
                    $data[$x]['jabatan_penilai'][$a]['threshold']=$list_mapping['threshold'];

                    $a++;
                    $id_jabatan_peserta_before=(int)$list_mapping['id_jabatan_peserta'];
                }
            }

            return [
                'status'=>$status, 
                'msg'=>$msg,
                'jumlah'=>$jumlah,
                'signarture'=>$signature,
                'data'=>$data_jabatan
            ];
        }

        public function removeMappingJabatanPeriode($id_trans_mapping, $id_periode){
            $status=false;
            $msg="";
            $get_periode=Tahun_penilaian::where('IdTahunPenilaian', $id_periode)->first();
            if(!is_null($get_periode)){
                $proses_id=(int)$get_periode['proses_id'];
                if($proses_id <= 3){
                    $get_mapping=Trans_mapping_jabatan_periode::where('id', $id_trans_mapping)
                                        ->where('id_periode', $id_periode)
                                        ->first();
                    if(!is_null($get_mapping)){
                        if($get_mapping->delete()){
                            $msg="Berhasil Menghapus data";
                            $status=true;
                        }else{
                            $msg="Terjadi kesalahan sistem saat menghapus data";
                        }
                    }else{
                        $msg="Data Mapping Jabatan tidak ditemukan";
                    }
                }else{
                    $msg="Tidak dapat menghapus data. Periode ini sudah berjalan";
                }
            }else{
                $msg="Data Periode tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function regenerateMappingJabatanPeriode($id_periode){
            $data=[];
            $msg="";
            $status=false;
            $get_data=Tahun_penilaian::where('IdTahunPenilaian', $id_periode)->first();
            if(!is_null($get_data)){
                $proses_id=(int)$get_data['proses_id'];
                if($proses_id <= 3){
                    try{
                        DB::beginTransaction();
                            Trans_mapping_jabatan_periode::where('id_periode', $id_periode)->delete();
                            $get_data_mapping=Tref_mapping_jabatan::where('active', true)->get();
                            $jlh_mapping=$get_data_mapping->count();
                            if($jlh_mapping > 0){
                                $data_mapping=[];
                                foreach($get_data_mapping as $list_mapping){
                                    $data_mapping[]=[
                                        'id_periode'=>$id_periode,
                                        'id_mapping_jabatan'=>$list_mapping['id']
                                    ];
                                }
                                DB::table('trans_mapping_jabatan_periode')->insert($data_mapping);
                                DB::commit();
                                $status=true;
                                $msg="Data Berhasil di update";
                            }else{
                                DB::rollBack();
                                $msg="Tidak dapat memperbaharui data. Master Mapping Jabatan belum ada";
                            }
                    }catch(\Exception $e){
                        $msg=$e->getMessage();
                        DB::rollBack();
                    }
                }else{
                    $msg="Tidak bisa mengubah data. Periode ini telah berjalan";
                }
            }else{
                $msg="Data Periode tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getAllPeriode(){
            $get_data=Cache::store('redis')->remember('all_periode', 3600*24*365, function(){
                return Tahun_penilaian::all();
            });
            return $get_data;
        }

    }

?>