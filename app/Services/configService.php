<?php
    namespace App\Services;

    use App\Models\Idtref_roles;
    use App\Models\Tahun_penilaian;
    use App\Models\Trans_peserta_zonasi;
    use App\Models\Tref_users;
    use App\Models\Tref_mapping_jabatan;
    use App\Models\Tref_jabatan_peserta;
    use App\Models\V_kelompok_jabatan;
    use Illuminate\Support\Facades\DB;
    use App\Models\Tref_bobot_penilaian;
    use App\Models\Tref_jawaban_bundle;
    use App\Models\Tref_pertanyaan;
    use Vinkla\Hashids\Facades\Hashids;
    use App\Models\Variable_pertanyaan;
    use GuzzleHttp\Promise\Is;
    use Illuminate\Support\Facades\Cache;

    class configService{
        public function listRole($page){
            $data=array();
            $limit = 15;
            $total=Idtref_roles::count();
            // $total=$get_data->count();
            $jumlahHalaman=ceil($total / $limit);
            if($page > $jumlahHalaman || $page <= 0 || is_null($page)){
                $page=1;
            }
            $skip=$page * $limit - $limit;
            if($total > 0){
                $get_data=Idtref_roles::skip($skip)->take($limit)->get();
                $x=0;
                foreach($get_data as $list){
                    $data[$x]['role_id']=$list['IdRole'];
                    $data[$x]['code_role']=$list['code'];
                    $data[$x]['rolename']=$list['rolename'];
                    $daata[$x]['active']=$list['is_active'];
                    $x++;
                }
            }

            return [
                'jumlah_halaman'=>$jumlahHalaman,
                'total'=>$total,
                'page'=>$page,
                'no'=>$skip+1,
                'data'=>$data
            ];
        }

        public function saveRole($request){
            $save=false;
            try{
                DB::beginTransaction();
                    $new_role=new Idtref_roles;
                    $new_role->rolename=$request->rolename;
                    $new_role->is_active=$request->active === "Y" ? true : false;
                    $save=$new_role->save();
                    $id=$new_role->id;
                    $code="r-00".$id;
                    $get_data=Idtref_roles::where('id', $id)->first();
                    $get_data->code=$code;
                    $save=$get_data->update();
                    $msg="Berhasil menyimpan data";
                    $save=true;
                DB::commit();
            }catch(\Exception $e){
                DB::rollBack();
                $msg="Terjadi kesalahan pada sistem ".$e->getMessage();
            }
            
            return [
                'status'=>$save,
                'msg'=>$msg
            ];
        }

        public function getDetilRole($role_id){
            $get_data=Idtref_roles::where('IdRole', $role_id)->first();
            if(!is_null($get_data)){
                $role_id=$get_data['IdRole'];
                $code_role=$get_data['code'];
                $rolename=$get_data['rolename'];
                $active=$get_data['is_active'];
                return [
                    'status'=>true,
                    'message'=>'',
                    'data'=>[
                        'role_id'=>$role_id,
                        'code_role'=>$code_role,
                        'rolename'=>$rolename,
                        'active'=>$active
                    ]
                ];
            }
            return [ 
                'status'=>false,
                'message'=> 'Data not found',
                'data'=>[]
            ];
        }

        public function updateRole($request){
            $status=false;
            $msg="Tidak dapat melakukan perubahan data";
            $get_data=Idtref_roles::where('IdRole', $request->role_id)->first();
            if(!is_null($get_data)){
                $get_data->rolename=$request->rolename;
                $get_data->is_active=$request->active === "Y" ? true : false;
                // $get_data->code="r-00".$request->role_id;
                $status=$get_data->update();
                if($status){
                    $msg="Berhasil mengubah data";
                }
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function deleteRole($request){
            $status=false;
            $get_data=Idtref_roles::where('IdRole', $request->role_id)->first();
            if(!is_null($get_data)){
                $get_user=Tref_users::where('IdRole', $request->role_id)->count();
                if($get_user > 0){
                    $msg="Tidak dapat menghapus data ini. Data sedang digunakan oleh user";
                }else{
                    $status=$get_data->delete();
                    if($status){
                        $msg="Berhasil menghapus data";
                    }else{
                        $msg="Terjadi kesalahan sistem saat menghapus data";
                    }
                }
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getKelompokJabatan($page){
            $limit=10;
            $data=[];
            $keterangan="";
            $jumlahHalaman=1;
            $get_data_params=null;
            $get_data=Tref_jabatan_peserta::whereRaw('id_jabatan_gabungan is null');
            $total=(clone $get_data)->count();

            $get_jabatan_digabung=Cache::store('redis')->remember('jabatan_peserta_digabung', 3600*24*365, function(){
                return Tref_jabatan_peserta::whereRaw('id_jabatan_gabungan is not null')
                                    ->orderBy('id_jabatan_gabungan', 'asc')    
                                    ->get();
            });
            if(is_numeric($page)){
                $jumlahHalaman=ceil($total / $limit);
                if($page > $jumlahHalaman){
                    $page=1;
                }
                $skip=$page*$limit-$limit;
                $get_data_params=(clone $get_data)->skip($skip)->take($limit)->get();
            }elseif(!is_numeric($page) && $page === "getall"){
                $get_data_params=Cache::store('redis')->remember('get_all_jabatan', 3600*24*365, function () {
                    return Tref_jabatan_peserta::whereRaw('id_jabatan_gabungan is null')->where('active',true)->get(); 
                });
            }
            $jabatan_gabungan=[];
            if(!is_null($get_data_params)){
                $id_jabatan_gabungan_before=null;
                foreach($get_jabatan_digabung as $list_gabungan){
                    if(is_null($id_jabatan_gabungan_before) || $id_jabatan_gabungan_before !== $list_gabungan['id_jabatan_gabungan']){
                        $a=0;
                    }
                    $jabatan_gabungan["gabungan_{$list_gabungan['id_jabatan_gabungan']}"][$a]['nama_jabatan']=$list_gabungan['jabatan'];
                    $jabatan_gabungan["gabungan_{$list_gabungan['id_jabatan_gabungan']}"][$a]['id_jabatan']=Hashids::encode($list_gabungan['id'])."-".Hashids::encode($list_gabungan['id_jabatan_gabungan'])."-".Hashids::encode($list_gabungan['id_kelompok_jabatan']);
                    $id_jabatan_gabungan_before=$list_gabungan['id_jabatan_gabungan'];
                    $a++;
                }
                $x=0;
                foreach($get_data_params as $list_data){
                    $keterangan=""; 
                    $data[$x]['id']=Hashids::encode($list_data['id']);
                    $data[$x]['jabatan']=$list_data['jabatan'];
                    $data[$x]['active']=$list_data['active'];
                    if((int)$list_data['id_kelompok_jabatan'] === 0 && (int)$list_data['active'] === 1){
                        $jumlah_gabungan=count($jabatan_gabungan["gabungan_{$list_data['id']}"]);
                        $keterangan.="Gabungan Jabatan dari : ";
                        for($i=0;$i<$jumlah_gabungan;$i++){
                            $keterangan.=$jabatan_gabungan["gabungan_{$list_data['id']}"][$i]['nama_jabatan']." ";
                            if($i !== $jumlah_gabungan-1){
                                $keterangan .="dan ";
                            }
                        }
                        // $data[$x]['jabatan_gabungan']=$jabatan_gabungan;
                    }
                    $data[$x]['keterangan']=$keterangan;
                    $x++;
                }
            }

            return [
                'jumlah'=>count($data),
                'data'=>$data,
                'page'=>$page,
                'jumlah_halaman'=>$jumlahHalaman,
            ];
        }

        public function getKelompokJabatanDetil($id_jabatan){
            $status=false;
            $msg="";
            $signature="";
            $data=[];
            $get_jabatan=Tref_jabatan_peserta::where('id', $id_jabatan)
                                        ->whereRaw('id_jabatan_gabungan is null')
                                        ->first();
            $jabatan_digabung=[];
            if(!is_null($get_jabatan)){
                $data['token_jabatan']=Hashids::encode($get_jabatan['id']);
                $data['jabatan']=$get_jabatan['jabatan'];
                $data['active']=$get_jabatan['active'];
                $data['jabatan_digabung']=[];
                $get_gabungan=Tref_jabatan_peserta::where('id_jabatan_gabungan', $get_jabatan['id'])->get();
                if($get_gabungan->count() > 0){
                    foreach($get_gabungan as $list_gabungan){
                        //id_jabatan_peserta - id_jabatan_induk - id_kelompok_jabatan_peserta
                        $jabatan_digabung[]=[
                            'token_jabatan_gabungan'=>Hashids::encode($list_gabungan['id'])."-".Hashids::encode($get_jabatan['id'])."-".Hashids::encode($list_gabungan['id_kelompok_jabatan']),
                            'jabatan'=>$list_gabungan['jabatan'],
                        ];
                    }
                    $data['jabatan_digabung']=$jabatan_digabung;
                }
                $signature=generateSignature(Hashids::encode($id_jabatan));
            }else{
                $msg="Data tidak bisa diedit";
            }

            return[
                'status'=>$status,
                'msg'=>$msg,
                "signature"=>$signature,
                'data'=>$data
            ];
        }

        public function saveKelompokJabatan($request, $id_kelompok_jabatan){
            $status=false;
            $check=Tref_jabatan_peserta::where('id_kelompok_jabatan', $id_kelompok_jabatan)->first();
            if(is_null($check)){
                $jabatan_peserta=new Tref_jabatan_peserta;
                $jabatan_peserta->id_kelompok_jabatan=$id_kelompok_jabatan;
                $jabatan_peserta->jabatan=$request->nama_jabatan;
                if($jabatan_peserta->save()){
                    $status=true;
                    $msg="Berhasil menyimpan data";
                    Cache::store('redis')->forget('get_all_jabatan');
                    Cache::store('redis')->forget('jabatan_peserta_digabung');
                }else{
                    $msg="Terjadi  kesalahan sistem saat mengubah data";
                }
            }else{
                $msg="Data kelompok jabatan ini sudah ada";
            }
            
            return[
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function gabungkanJabatan($id_jabatan_arr, $nama_jabatan){
            $status = false;
            $jumlah_jabatan_gabungan=count($id_jabatan_arr);
            if($jumlah_jabatan_gabungan > 1){
                $data_db=Tref_jabatan_peserta::whereIn('id', $id_jabatan_arr)
                                ->where('active', true)
                                ->whereRaw('id_jabatan_gabungan is null')
                                ->where('id_kelompok_jabatan', '<>', 0);
                $jumlah_data_db=(clone $data_db)->count();
                if($jumlah_data_db === $jumlah_jabatan_gabungan){
                    try{
                        //insert new jabatan
                        DB::beginTransaction();
                            $new_jabatan=new Tref_jabatan_peserta;
                            $new_jabatan->id_kelompok_jabatan=0;
                            $new_jabatan->jabatan=$nama_jabatan;
                            $new_jabatan->active=true;
                            $new_jabatan->save();
                            $id_new_jabatan=$new_jabatan->id;

                            $updated=(clone $data_db)->update(['id_jabatan_gabungan' => $id_new_jabatan]);
                            if($updated === $jumlah_data_db){
                                DB::commit();
                                $status=true;
                                Cache::store('redis')->forget("jabatan_peserta_digabung");
                                Cache::store('redis')->forget('get_all_jabatan');
                                $msg="Berhasil menggabungkan ";
                            }else{
                                throw new \Exception("Data tidak bisa di gabungkan");
                            }
                    }catch(\Exception $e){
                        DB::rollBack();
                        $msg=$e->getMessage();
                    }
                }else{
                    $msg="Tidak dapat digabungkan. Data tidak valid atau sudah gabungan";
                }
            }else{
                $msg="Tidak dapat digabungkan. Jumlah harus lebih dari 1";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function updateKelompokJabatan($id_jabatan_gabungan_arr, $id_jabatan, $jabatan, $status_aktif){
            $status=false;
            $jumlah_gabungan=count($id_jabatan_gabungan_arr);
            $get_jabatan=Tref_jabatan_peserta::where('id', $id_jabatan)->first();
            if(!is_null($get_jabatan)){
                if((int)$get_jabatan['id_kelompok_jabatan'] === 0){
                    //jabatan gabungan
                    if($jumlah_gabungan > 0){
                        $jumlah_jabatan_gabungan=Tref_jabatan_peserta::whereIn('id', $id_jabatan_gabungan_arr)
                                    ->where('active', true)
                                    ->count();
                        if($jumlah_gabungan === $jumlah_jabatan_gabungan){
                            $get_existed=Tref_jabatan_peserta::where('id_jabatan_gabungan', $id_jabatan)->get();
                            if($get_existed->count() > 1){
                                $existed_jabatan=[];
                                foreach($get_existed as $list_existed){
                                    $existed_jabatan[]=$list_existed['id'];
                                }
                                $lookup_jabatan_gabungan=array_flip($id_jabatan_gabungan_arr);
                                $remove_jabatan=[];
                                for($i_existed = 0; $i_existed<$get_existed->count(); $i_existed++){
                                    if(!isset($lookup_jabatan_gabungan[$existed_jabatan[$i_existed]])){
                                        $remove_jabatan[]=$existed_jabatan[$i_existed];
                                    }
                                }
                                try{
                                    DB::beginTransaction();
                                        //remove
                                        Tref_jabatan_peserta::whereIn('id', $remove_jabatan)->update(['id_jabatan_gabungan'=>null]);
                                        Tref_jabatan_peserta::whereIn('id', $id_jabatan_gabungan_arr)->update(['id_jabatan_gabungan'=>$id_jabatan]);
                                        $get_jabatan->jabatan=$jabatan;
                                        $get_jabatan->active=$status_aktif === "Y" ? true : false;
                                        $get_jabatan->update(); 
                                    DB::commit();
                                    $status=true;
                                    $msg="Berhasil menyimpan data";
                                }catch(\Exception $e){
                                    DB::rollBack();
                                    $msg=$e->getMessage();
                                }
                                
                            }else{
                                $msg="Data Jabatan Gabungan ini tidak valid. Tidak ada 2 atau lebih jabatan yang digabung";
                            }
                        }else{
                            $msg="Data anda tidak valid ";
                        }
                            
                    }elseif($jumlah_gabungan === 0){
                        try{
                            DB::beginTransaction();
                                $pisahkan=Tref_jabatan_peserta::where('id_jabatan_gabungan', $id_jabatan)->update(['id_jabatan_gabungan'=>null]);
                                $get_jabatan->active=false;
                                $get_jabatan->update();
                            DB::commit();
                            $status=true;
                            $msg="Berhasil memisahkan jabatan";
                        }catch(\Exception $e){
                            DB::rollBack();
                            $msg=$e->getMessage();
                        }
                    }else{
                        $msg="Jabatan yang digabung harus ada";
                    }
                }else{
                    //bukan jabatan gabungan
                    if($jumlah_gabungan === 0){
                        $get_jabatan->active=$status_aktif === "Y" ? true : false;
                        if($get_jabatan->update()){
                            $status=true;
                            $msg="Berhasil menyimpan data";
                        }else{
                            $msg="Terjadi kesalahan data";
                        }
                    }else{
                        $msg="Data Jabatan gabungan tidak boleh ada";
                    }
                }
            }else{
                $msg="Data jabatan tidak ditemukan";
            }

            if($status === true){
                Cache::store('redis')->forget('get_all_jabatan');
                Cache::store('redis')->forget('jabatan_peserta_digabung');
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
            
        }

        // public function changeActiveKelompokJabatan($id_jabatan_peserta, $status_kelompok_jabatan){
        //     $status=false;
        //     $get_data=Tref_jabatan_peserta::where('id', $id_jabatan_peserta)->first();
        //     if(!is_null($get_data)){
        //         $get_data->active= $status_kelompok_jabatan === "Y" ? true : false;
        //         if($get_data->update()){
        //             $status=true;
        //             $msg="Data kelompok jabatan berhasil diubah";
        //         }else{
        //             $msg="Terjadi kesalahan sistem saat mengubah data";
        //         }
        //     }else{
        //         $msg="Data tidak ditemukan ".$id_jabatan_peserta;
        //     }

        //     return [
        //         'status'=>$status,
        //         'msg'=>$msg
        //     ];
        // }

        public function getListMappingJabatan(){
            $get_data_peserta=Tref_jabatan_peserta::where('active', true)
                                ->whereRaw('id_jabatan_gabungan is null')    
                                ->get();
            $get_data_mapping=Tref_mapping_jabatan::join('tref_jabatan_peserta as a', 'a.id', '=', 'tref_mapping_jabatan.id_jabatan_peserta')
                                                ->join('tref_jabatan_peserta as b', 'b.id', '=', 'tref_mapping_jabatan.id_jabatan_penilai')
                                                ->where('tref_mapping_jabatan.active', true)
                                                ->select('tref_mapping_jabatan.*', 'a.jabatan as jabatan_peserta', 'b.jabatan as jabatan_penilai')
                                                ->get();
            $data_penilaian=[];
            $x=$y=0;
            $mapping=[];

            foreach($get_data_mapping as $list_mapping){
                $data_penilaian[$x]['id_mapping']=$list_mapping['id'];
                $data_penilaian[$x]['id_jabatan_peserta']=$list_mapping['id_jabatan_peserta'];
                $data_penilaian[$x]['jabatan_peserta']=$list_mapping['jabatan_peserta'];
                $data_penilaian[$x]['id_jabatan_penilai']=$list_mapping['id_jabatan_penilai'];
                $data_penilaian[$x]['jabatan_penilai']=$list_mapping['jabatan_penilai'];
                $data_penilaian[$x]['threshold']=$list_mapping['threshold'];
                $data_penilaian[$x]['active']=$list_mapping['active'];
                $x++;
            }

            $jumlah_penilai=count($data_penilaian);

            foreach($get_data_peserta as $list_data){
                $mapping[$y]['jabatan_peserta']=$list_data['jabatan'];
                $mapping[$y]['id_jabatan_peserta']=Hashids::encode($list_data['id']);
                $mapping[$y]['penilai']=[];
                $index_mapping=0;
                for($a=0;$a<$jumlah_penilai;$a++){
                    if((int)$list_data['id'] === (int)$data_penilaian[$a]['id_jabatan_peserta']){
                        $mapping[$y]['penilai'][$index_mapping]['id_mapping']=Hashids::encode($data_penilaian[$a]['id_mapping']);
                        $mapping[$y]['penilai'][$index_mapping]['jabatan_penilai']=$data_penilaian[$a]['jabatan_penilai'];
                        $mapping[$y]['penilai'][$index_mapping]['threshold']=$data_penilaian[$a]['threshold'];
                        $mapping[$y]['penilai'][$index_mapping]['active']=$data_penilaian[$a]['active'] === 1 ? "Y" : "N";
                        $index_mapping++;
                    }
                }
                $y++;
            }

            return [
                'data_mapping'=>$mapping
            ];
        }

        public function saveMappingJabatan($id_jabatan_peserta, $id_jabatan_penilai, $threshold){
            //id_jabatan_peserta: id table jabatan_peserta
            //id_jabatan_penilai= id table jabatan_peserta
            // array_push($id_jabatan_penilai, $id_jabatan_peserta);
            $status=false;
            $append_data_peserta=false;
            if(!in_array($id_jabatan_peserta, $id_jabatan_penilai)){
                array_push($id_jabatan_penilai, $id_jabatan_peserta);
                $append_data_peserta=true;
            }
            $get_data=Tref_jabatan_peserta::whereIn('id', $id_jabatan_penilai)
                                    ->where('active', true)
                                    ->get();
            $jumlah=$get_data->count();
            if($jumlah > 0 && $jumlah === count($id_jabatan_penilai)){
                if($append_data_peserta === true){
                    $id_peserta_arr=explode(",", $id_jabatan_peserta);
                    $id_jabatan_penilai=array_diff($id_jabatan_penilai, $id_peserta_arr);
                   
                }
                $data=[];
                for($x=0;$x<count($id_jabatan_penilai);$x++){
                    $data[]=[
                        'id_jabatan_peserta'=>$id_jabatan_peserta,
                        'id_jabatan_penilai'=>$id_jabatan_penilai[$x],
                        'active'=>true,
                        'threshold'=>$threshold[$x]
                    ];

                    $data_bobot[]=[
                        'id_jabatan_peserta'=>$id_jabatan_peserta,
                        'id_jabatan_penilai'=>$id_jabatan_penilai[$x],
                        'active'=>true,
                        'bobot'=>null
                    ];
                }

                //check existed
                $jlh_existed_mapping=Tref_mapping_jabatan::where('id_jabatan_peserta', $id_jabatan_peserta)
                                                    ->whereIn('id_jabatan_penilai', $id_jabatan_penilai)
                                                    ->count();
                if($jlh_existed_mapping > 0){
                    $msg="Data Observee yang dimasukkan sudah ada pada table mapping jabatan";
                }else{
                    try{
                        DB::beginTransaction();
                            DB::table('tref_mapping_jabatan')->insert($data);
                            DB::table('tref_bobot_penilaian')->insert($data_bobot);
                        DB::commit();
                        $status=true;
                        $msg="Data berhasil disimpan";
                    }catch(\Exception $e){
                        DB::rollBack();
                        $msg="Data tidak dapat disave ".$e->getMessage();
                    }
                }
            }else{
                $msg="Data tidak ditemukan atau data tidak sesuai";
            }
            
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function updateMappingJabatan($id_jabatan_peserta, $id_mapping_jabatan, $status, $threshold, $id_jabatan_penilai, $new_mapping){
            $status=false;
            $append_data_peserta=false;
            //gabungkan jabatan untuk
            if(!in_array($id_jabatan_peserta, $id_jabatan_penilai)){
                $append_data_peserta=true;
                array_push($id_jabatan_penilai, $id_jabatan_peserta);
            }

            $total_data_peserta=Tref_jabatan_peserta::whereIn('id', $id_jabatan_penilai)->count();
            if($total_data_peserta === count($id_jabatan_penilai)){
                if($append_data_peserta === true){
                    //set id_jabatan_peserta jadi array
                    $explode=explode(',', $id_jabatan_peserta);
                    //pisahkan id_jabatan_peserta dari id_jabatan_penilai
                    $id_jabatan_penilai=array_diff($id_jabatan_penilai, $explode);
                }

                $get_data_peserta=Tref_mapping_jabatan::where('id_jabatan_peserta', $id_jabatan_peserta)
                                                    ->where('active', true)
                                                    ->get();
                $get_data_bobot=Tref_bobot_penilaian::where('id_jabatan_peserta', $id_jabatan_peserta)
                                                ->where('active', true)->get();
                if($get_data_peserta->count() === 0 || $get_data_bobot->count() === 0){
                    $msg="Data Mapping dan bobot tidak ditemukan ";
                }else{
                    $list_existed_penilai=[];
                    $id_mapping_jabatan_peserta=[];
                    $x=0;
                    foreach($get_data_peserta as $list_peserta){
                        // $list_existed_penilai[$x]['id_jabatan_penilai']=$list_peserta['id_jabatan_penilai'];
                        // $list_existed_penilai[$x]['threshold']=$list_peserta['threshold'];
                        $id_mapping_jabatan_peserta[]=$list_peserta['id'];
                        $list_existed_penilai[]=$list_peserta['id_jabatan_penilai'];
                    }
                    foreach($get_data_bobot as $list_bobot){
                        $id_bobot_penilaian[]=$list_bobot['id'];
                        $list_existed_bobot_penilai[]=$list_bobot['id_jabatan_penilai'];
                    }

                    //set data jabatan penilai yang masuk
                    $data_penilai=[];
                    for($x=0;$x<count($id_jabatan_penilai);$x++){
                        $data_penilai[]=[
                            'id_jabatan_peserta'=>$id_jabatan_peserta,
                            'id_jabatan_penilai'=>$id_jabatan_penilai[$x],
                            'threshold'=>$threshold[$x],
                            'active'=>true
                        ];
                        $data_bobot[]=[
                            'id_jabatan_peserta'=>$id_jabatan_peserta,
                            'id_jabatan_penilai'=>$id_jabatan_penilai[$x],
                            'bobot'=>null,
                            'active'=>true
                        ];
                        $last_index=count($data_penilai)-1;
                        if($id_mapping_jabatan[$x] !== "new"){
                            $data_penilai[$last_index]['id']=$id_mapping_jabatan[$x];
                        }
                    }


                    $lookup_existed=array_flip($list_existed_penilai);
                    $jlh_penilai_input=count($id_jabatan_penilai);
                    $jlh_existed=count($list_existed_penilai);
                    $jlh_existed_bobot=count($list_existed_bobot_penilai);
                    $inserted_penilai=[];
                    $inserted_bobot=[];
                    $data_diupdate=[];
                    for($x=0;$x<$jlh_penilai_input;$x++){
                        //kalau tida ada data existed di array jabatan penilai maka diinsert
                        if(!isset($lookup_existed[$id_jabatan_penilai[$x]])){
                            $inserted_penilai[]=$data_penilai[$x];
                            $inserted_bobot[]=$data_bobot[$x];
                        }else{
                            $data_diupdate[]=$data_penilai[$x];
                        }
                    }

                    
                    $jlh_insert=count($inserted_penilai);
                    if($jlh_insert === (int)$new_mapping){
                        $nonaktif_panilai=[];
                        $nonaktif_bobot=[];
                        $lookup_jabatan_penilain=array_flip($id_jabatan_penilai);
                        for($x=0;$x<$jlh_existed;$x++){
                            if(!isset($lookup_jabatan_penilain[$list_existed_penilai[$x]])){
                                $nonaktif_panilai[]=$id_mapping_jabatan_peserta[$x];
                                $nonaktif_bobot[]=$id_bobot_penilaian[$x];
                            }
                        }

                        try{
                            DB::beginTransaction();
                                
                                DB::table('tref_mapping_jabatan')->insert($inserted_penilai);
                                DB::table('tref_bobot_penilaian')->insert($inserted_bobot);
                                if(count($nonaktif_bobot) > 0 && count($nonaktif_panilai) > 0){
                                    Tref_mapping_jabatan::whereIn('id', $nonaktif_panilai)->update(['active'=>false]);
                                    Tref_bobot_penilaian::whereIn('id', $nonaktif_bobot)->update(['active'=>false]);
                                }
                                foreach($data_diupdate as $row){
                                    $get_data=Tref_mapping_jabatan::where('id', $row['id'])->first();
                                    if(!is_null($get_data)){
                                        $get_data->id_jabatan_peserta=$row['id_jabatan_peserta'];
                                        $get_data->id_jabatan_penilai=$row['id_jabatan_penilai'];
                                        $get_data->threshold=$row['threshold'];
                                        $get_data->active=true;
                                        $get_data->update();
                                    }else{
                                        DB::rollBack();
                                        $msg="Data tidak ditemukan. Data Mapping Jabatan yang mau diupdate tidak ditemukan, terjadi ketidak konsistensian data";
                                    }
                                }
                            DB::commit();
                            $status=true;
                            $msg="Data berhasil diperbaharui";
                        }catch(\Exception $e){
                            DB::rollBack();
                            $msg="Error database: ".$e->getMessage();
                        }
                    }else{
                        $msg="Data Input dan new Mapping tidak konsisten ".$jlh_insert." ".$new_mapping;
                    }
                }
            }else{
                $msg="Data Peserta atau Penilai ada yang tidak ditemukan pada Referensi Jabatan Peserta";
            }
            
            
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getKelompokJabatanSIKEP(){
            $get_data=V_kelompok_jabatan::all();
            $x=0;
            $data=[];
            foreach($get_data as $list_data){
                $data[$x]['id_kelompok_jabatan']=Hashids::encode($list_data['IdKelompokJabatan']);
                $data[$x]['nama_jabatan']=$list_data['NamaKelompokJabatan'];
                $x++;
            }

            return $data;
        }

        public function getMappingJabatanByIdJabatanPeserta($id_jabatan_peserta){
            $data=[];
            $get_jabatan=Tref_jabatan_peserta::where('id', $id_jabatan_peserta)->first();
            if(!is_null($get_jabatan)){
                $data['id_jabatan_peserta']=Hashids::encode($get_jabatan['id']);
                $data['jabatan_peserta']=$get_jabatan['jabatan'];
                $data['penilai']=[];
                $get_data=Tref_mapping_jabatan::join('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'tref_mapping_jabatan.id_jabatan_peserta')
                            ->join('tref_jabatan_peserta as tjp2', 'tjp2.id', '=', 'tref_mapping_jabatan.id_jabatan_penilai')
                            ->select('tref_mapping_jabatan.*', 'tjp.jabatan as jabatan_peserta', 'tjp2.jabatan as jabatan_penilai')
                            ->where('tref_mapping_jabatan.id_jabatan_peserta', $id_jabatan_peserta)->get();
                $jumlah=$get_data->count();
                $x=0;
                if($jumlah > 0){
                    foreach($get_data as $list_data){
                        $data['penilai'][$x]['id_mapping']=Hashids::encode($list_data['id']);
                        $data['penilai'][$x]['id_jabatan_penilai']=Hashids::encode($list_data['id_jabatan_penilai']);
                        $data['penilai'][$x]['jabatan_penilai']=$list_data['jabatan_penilai'];
                        $data['penilai'][$x]['threshold']=$list_data['threshold'];
                        $data['penilai'][$x]['active'] = $list_data['active'] === 1 ? 'Y' : 'N';
                        $x++;
                    }
                }
            }else{
                $msg="Data Jabatan tidak ditemukan";
            }
            

            return [
                'jumlah'=>$jumlah,
                'data'=>$data
            ];
        }

        public function getAllBobot($page){
            $data=array();
            $jabatan_penilai=array();
            $limit = 15;
            $total=Tref_jabatan_peserta::where('active', true)
                        ->count();
            $jumlahHalaman=ceil($total / $limit);
            if($page < 1 || $page > $jumlahHalaman){
                $page=1;
            }
            $skip=$page * $limit - $limit;
            $get_jabatan=Tref_jabatan_peserta::where('active', true)
                            ->whereRaw('id_jabatan_gabungan is null')
                            ->get();
            $x=$y=0;
            if($total > 0){
                $get_data=Tref_bobot_penilaian::join('tref_jabatan_peserta as tjp1', 'tjp1.id', '=', 'tref_bobot_penilaian.id_jabatan_peserta')
                                ->join('tref_jabatan_peserta as tjp2', 'tjp2.id', '=', 'tref_bobot_penilaian.id_jabatan_penilai')
                                ->select('tref_bobot_penilaian.*', 'tjp1.jabatan as jabatan_peserta', 'tjp2.jabatan as jabatan_penilai')
                                ->where('tref_bobot_penilaian.active', true)
                                ->get();
               
                foreach($get_data as $list_data){
                    $jabatan_penilai[$y]['token_id']=Hashids::encode($list_data['id']);
                    $jabatan_penilai[$y]['jabatan_peserta']=$list_data['jabatan_peserta'];
                    $jabatan_penilai[$y]['id_jabatan_peserta']=$list_data['id_jabatan_peserta'];
                    $jabatan_penilai[$y]['jabatan_penilai']=$list_data['jabatan_penilai'];
                    $jabatan_penilai[$y]['id_jabatan_penilai']=$list_data['id_jabatan_penilai'];
                    $jabatan_penilai[$y]['active']=$list_data['active'];
                    $jabatan_penilai[$y]['bobot']=$list_data['bobot'];
                    $y++;
                }
            }
            foreach($get_jabatan as $list_jabatan){
                $data[$x]['token_peserta']=Hashids::encode($list_jabatan['id']);
                $data[$x]['jabatan_peserta']=$list_jabatan['jabatan'];
                $data[$x]['jabatan_penilai']=[];
                $jlh_jabatan_penilai=count($jabatan_penilai);
                $index_mapping=0;
                for($a=0;$a<$jlh_jabatan_penilai;$a++){
                    if((int)$jabatan_penilai[$a]['id_jabatan_peserta'] === (int)$list_jabatan['id']){
                        $data[$x]['jabatan_penilai'][$index_mapping]['token']=$jabatan_penilai[$a]['token_id'];
                        $data[$x]['jabatan_penilai'][$index_mapping]['active']=$jabatan_penilai[$a]['active'] === 1 ? 'Y' : 'N';
                        $data[$x]['jabatan_penilai'][$index_mapping]['jabatan']=$jabatan_penilai[$a]['jabatan_penilai'];
                        $data[$x]['jabatan_penilai'][$index_mapping]['bobot']=$jabatan_penilai[$a]['bobot'];
                        $index_mapping++;
                    }
                }
                $x++;
            }
            return [
                'total'=>$total,
                'jumlahHalaman'=>$jumlahHalaman,
                'page'=>$page,
                'data'=>$data,
            ];
        }


        //sudah tidak digunakan lagi
        public function saveBobot($data, $id_jabatan_penilai, $id_jabatan_peserta){
            $save=false;
            $check_jabatan=true;
            $jlh_penilai=count($id_jabatan_penilai);
            for($a=0;$a<$jlh_penilai;$a++){
                $id_jabatan_arr=[$id_jabatan_peserta, $id_jabatan_penilai[$a]];
                $jumlah_jabatan=Tref_jabatan_peserta::whereIn('id', $id_jabatan_arr)->count();
                if($id_jabatan_peserta !== $id_jabatan_penilai[$a] && $jumlah_jabatan < 2 || $id_jabatan_peserta === $id_jabatan_penilai[$a] && $jumlah_jabatan !== 1){
                    $msg="Data Jabatan Peserta atau Penilai tidak valid ";
                    $check_jabatan=false;
                    break;
                }
                $check_data=Tref_bobot_penilaian::where('id_jabatan_peserta', $id_jabatan_peserta)
                                ->where('id_jabatan_penilai', $id_jabatan_penilai[$a])
                                ->first();
                if(!is_null($check_data)){
                    $msg="Salah Satu atau Seluruh Data Bobot yang anda kirim sudah ada";
                    $check_jabatan=false;
                    break;
                }
            }

            if($check_jabatan === true){
                try{
                    DB::beginTransaction();
                        DB::table('tref_bobot_penilaian')->insert($data);
                    DB::commit();
                    $save=true;
                    $msg="Berhasil menyimpan data";
                }catch(\Exception $e){
                    DB::rollBack();
                    $msg=$e->getMessage();
                }
            }
            
            return [
                'status'=>$save,
                'msg'=>$msg
            ];
        }

        public function getDetilBobot($id_jabatan_peserta){
            $msg="";
            $data=array();
            $status=false;
            $get_jabatan=Tref_jabatan_peserta::where('id', $id_jabatan_peserta)->first();
            if(!is_null($get_jabatan)){
                $status=true;
                $data['token_peserta']=Hashids::encode($get_jabatan['id']);
                $data['jabatan_peserta']=$get_jabatan['jabatan'];
                $data['jabatan_penilai']=[];
                $get_data=Tref_bobot_penilaian::join('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'tref_bobot_penilaian.id_jabatan_penilai')
                            ->select('tref_bobot_penilaian.*', 'tjp.jabatan as jabatan_penilai')
                            ->where('id_jabatan_peserta', $id_jabatan_peserta)->get();
                $a=0;
                foreach($get_data as $list_data){
                   
                    $data['jabatan_penilai'][$a]['token_id']=Hashids::encode($list_data['id']);
                    $data['jabatan_penilai'][$a]['token_id_jabatan_penilai']=Hashids::encode($list_data['id_jabatan_penilai']);
                    $data['jabatan_penilai'][$a]['jabatan_penilai']=$list_data['jabatan_penilai'];
                    $data['jabatan_penilai'][$a]['active']=$list_data['active'] === 1 ? 'Y' : 'N';
                    $data['jabatan_penilai'][$a]['bobot']=$list_data['bobot'];
                    $a++;
                }
            }else{
                $msg="Data Jabatan tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
        }

        public function updateDataBobot($id_jabatan_peserta, $id_jabatan_penilai, $id_bobot_arr, $bobot_arr, $new_mapping){
            $status=false;
            $append_data=false;
            if(!in_array($id_jabatan_peserta, $id_jabatan_penilai)){
                array_push($id_jabatan_penilai, $id_jabatan_peserta);
                $append_data=true;
            }
            $jumlah_jabatan_peserta=Tref_jabatan_peserta::whereIn('id', $id_jabatan_penilai)->count();

            if($jumlah_jabatan_peserta === count($id_jabatan_penilai)){
                if($append_data){
                    $id_peserta_arr=explode(",", $id_jabatan_peserta);
                    $id_jabatan_penilai=array_diff($id_jabatan_penilai, $id_peserta_arr);
                }
                $get_existed=Tref_bobot_penilaian::where('id_jabatan_peserta', $id_jabatan_peserta)->get();
                $existed_bobot=[];
                foreach($get_existed as $list_existed){
                    $existed_bobot[]=$list_existed['id'];
                }
                $jlh_bobot=count($existed_bobot);
                $lookup=array_flip($existed_bobot);
                $data_insert=[];
                $data_update=[];
                $data_delete=[];
                for($a=0;$a<count($id_bobot_arr);$a++){
                    if(!isset($lookup[$id_bobot_arr[$a]])){
                        $data_insert[]=[
                            'id_jabatan_peserta'=>$id_jabatan_peserta,
                            'id_jabatan_penilai'=>$id_jabatan_penilai[$a],
                            'bobot'=>$bobot_arr[$a],
                            'active'=>true
                        ];
                    }else{
                        $data_update[]=[
                            'id_bobot'=>$id_bobot_arr[$a],
                            'bobot'=>$bobot_arr[$a]
                        ];
                    }
                }
                
                $lookup_bobot=array_flip($id_bobot_arr);
                for($x=0;$x<$jlh_bobot;$x++){
                    if(!isset($lookup_bobot[$existed_bobot[$x]])){
                        $data_delete[]=$existed_bobot[$x];
                    }
                }
                if(count($data_insert) === (int)$new_mapping){
                    try{
                        DB::beginTransaction();
                            if(count($data_insert) > 0){
                                DB::table('tref_bobot_penilaian')->insert($data_insert);
                            }
                            if(count($data_delete) > 0){
                                $update=Tref_bobot_penilaian::whereIn('id', $data_delete)->update(['active'=>false]);
                            }
                            if(count($data_update) > 0){
                                for($x=0;$x<count($data_update);$x++){
                                    $get_data=Tref_bobot_penilaian::where('id', $data_update[$x]['id_bobot'])->first();
                                    if(!is_null($get_data)){
                                        $get_data->bobot=$data_update[$x]['bobot'];
                                        $get_data->active=true;
                                        $get_data->update();
                                    }else{
                                        $msg="Data tidak ditemuklan";
                                        throw new \Exception($msg);
                                    }
                                }
                            }else{
                                throw new \Exception("Tidak ada data yang diupdate");
                            }
                        DB::commit();
                        $status=true;
                        $msg="Berhasil mengubah data";
                    }catch(\Exception $e){
                        DB::rollBack();
                        $msg=$e->getMessage();
                    }
                }else{
                    $msg="Data tidak konsisten";
                }
            }else{
                $msg="Data peserta tidak konsisten";
            }
            return [
                'status'=>$status,
                'msg'=>$msg,
            ];
        }
        public function getListVariable($page){
            $data=array();
            $limit = 10;
            $total=Variable_pertanyaan::count();
            $jumlahHalaman=ceil($total / $limit);
            if($page > $jumlahHalaman){
                $page = 1;
            }
            $skip=$page * $limit - $limit;
            $get_data=Variable_pertanyaan::skip($skip)->take($limit)->get();
            if($total > 0){
                $x=0;
                foreach($get_data as $list_data){
                    $data[$x]['token_id']=Hashids::encode($list_data['id']);
                    $data[$x]['variable']=$list_data['variable'];
                    $data[$x]['kriteria']=$list_data['kriteria'];
                    $data[$x]['active']=$list_data['active'];
                    $x++;
                }
            }

            return [
                'jumlah_halaman'=>$jumlahHalaman,
                'page'=>$page,
                'total'=>$total,
                'data'=>$data
            ];
        }

        public function getAllVariable(){
            $data=[];
            $get_data=Variable_pertanyaan::where('active', true)->get();
            $total=$get_data->count();
            $x=0;
            foreach($get_data as $list_data){
                $data[$x]['token_variable']=Hashids::encode($list_data['id']);
                $data[$x]['variable']=$list_data['variable'];
                $x++;
            }
           return [
            'total'=>$total,
            'data'=>$data,
           ];
        }

        public function saveVariable($request){
            $status=false;
            try{
                 $clean = str_ireplace(
                    ['<br>', '<br/>', '<br />', '</p>', '<p>'],
                    ["\n", "\n", "\n", "\n", ""],
                    $request->kriteria
                );
                $allowed_tag='<p><b><i><u><ul><li><a><br>';
                $kriteria=strip_tags($clean, $allowed_tag);
               
                DB::beginTransaction();
                    $new_variable=new Variable_pertanyaan;
                    $new_variable->variable=strip_tags($request->variable);
                    $new_variable->kriteria=$kriteria;
                    $new_variable->save();
                DB::commit();
                $status=true;
                $msg="Berhasil menyimpan data variable";
            }catch(\Exception $e){
                DB::rollBack();
                $msg=$e->getMessage();
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getVariableById($id_variable){
            $status=false;
            $data=[];
            $msg="";
            $get_data=Variable_pertanyaan::where('id', $id_variable)->first();
            if(!is_null($get_data)){
                $status=true;
                $data['token_id']=Hashids::encode($get_data['id']);
                $data['variable']=$get_data['variable'];
                $data['kriteria']=$get_data['kriteria'];
                $data['active']=$get_data['active'] === 1 ? 'Y' : 'N';
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
        }

        public function updateVariablePertanyaan($request, $id_variable){
            $update=false;
            $clean = str_ireplace(
                ['<br>', '<br/>', '<br />', '</p>', '<p>'],
                ["\n", "\n", "\n", "\n", ""],
                $request->kriteria
            );
            $allowed_tag='<p><b><i><u><ul><li><a><br>';
            $kriteria=strip_tags($clean, $allowed_tag);
            $get_data=Variable_pertanyaan::where('id', $id_variable)->first();
            if(!is_null($get_data)){
                $get_data->variable=strip_tags($request->variable);
                $get_data->kriteria=$kriteria;
                $get_data->active=$request->status === "Y" ? 1 : 0;
                if($get_data->update()){
                    $update=true;
                    $msg="Berhasil mengubah data Variable Pertanyaean";
                }else{
                    $msg="Terjadi kesalahan sistem saat mengubah data";
                }
            }else{
                $msg="Data tidak ditemukan";
            }        
             
            return [
                'status'=>$update,
                'msg'=>$msg
            ];
        }

        public function getJawabanBundle($page){
            $limit = 10;
            $get_data=Tref_jawaban_bundle::select("bundle_code", "bundle_name")
                    ->distinct()
                    ->get();
            $total=$get_data->count();
            $jumlahHalaman=ceil($total / $limit);
            if($page > $jumlahHalaman){
                $page = 1;
            }
            $skip=$page * $limit - $limit;
            $bundle_code=[];
            $bundle_name=[];
            foreach($get_data as $list_data){
                $bundle_code[]=$list_data['bundle_code'];
                $bundle_name[]=$list_data['bundle_name'];
            }

            $get_jawaban=Tref_jawaban_bundle::where('active', true)->orderBy('point_jawaban', 'desc')->get();
            $x=0;
            $jumlah_bundle=count($bundle_code);
            $data=[];
            for($a=0;$a<$jumlah_bundle;$a++){
                $code=$bundle_code[$a];
                $data[$a]['bundle_code']=$code;
                $data[$a]['bundle_name']=$bundle_name[$a];
                $data[$a]['bundle_jawaban']=[];
                $mapping_bundle=0;
                foreach($get_jawaban as $list_jawaban){
                    if($list_jawaban['bundle_code'] === $bundle_code[$a]){
                        $data[$a]['bundle_jawaban'][$mapping_bundle]['token_bundle']=Hashids::encode($list_jawaban['id']);
                        $data[$a]['bundle_jawaban'][$mapping_bundle]['jawaban']=$list_jawaban['jawaban_text'];
                        $data[$a]['bundle_jawaban'][$mapping_bundle]['point']=$list_jawaban['point_jawaban'];
                        $mapping_bundle++;
                    }
                }
            }

            return [
                'page'=>$page,
                'jumlah_halaman'=>$jumlahHalaman,
                'total'=>$total,
                'data'=>$data
            ];
        }

        public function getAllBundleJawaban(){
            $get_data=Tref_jawaban_bundle::where('active', true)->orderBy('bundle_code', 'desc')->orderBy('point_jawaban', 'desc')->get();
            $data=[];
            $total=$get_data->count();
            if($total > 0){
                $x=0;
                $bundle_code_before="";
                foreach($get_data as $list_data){
                    if($bundle_code_before !== $list_data['bundle_code']){
                        $a=0;
                        if($bundle_code_before !== ""){
                             $x++;
                        }
                        $data[$x]['bundle_code']=$list_data['bundle_code'];
                        $data[$x]['bundle_name']=$list_data['bundle_name'];
                        $data[$x]['bundle_jawaban']=[];
                    }
                    $data[$x]['bundle_jawaban'][$a]['bundle_token']=Hashids::encode($list_data['id']);
                    $data[$x]['bundle_jawaban'][$a]['jawaban_text']=$list_data['jawaban_text'];
                    $data[$x]['bundle_jawaban'][$a]['point']=$list_data['point_jawaban'];
                    $a++;
                    $bundle_code_before=$list_data['bundle_code'];
                }
            }

            return [
                'total'=>$total,
                'data'=>$data
            ];
        }

        public function saveJawabanBundle($data_insert, $bundle_name){
            $status=false;
            $get_data=Tref_jawaban_bundle::where('bundle_name', $bundle_name)->first();
            if(is_null($get_data)){
                try{
                    DB::beginTransaction();
                        DB::table('tref_jawaban_bundle')->insert($data_insert);
                    DB::commit();
                    $status=true;
                    $msg="Berhasil menyimpan data Bundle Jawaban ".$bundle_name;
                }catch(\Exception $e){
                    DB::rollBack();
                    $msg=$e->getMessage();
                }
            }else{
                $msg="Nama Bundle sudah ada";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getJawabanBundleDetil($bundle_code){
            $status=false;
            $signature="";
            $data=[];
            $msg="";
            $get_data=Tref_jawaban_bundle::where('bundle_code', $bundle_code)
                        ->where('active', true)
                        ->orderBy('point_jawaban', 'desc')
                        ->get();
            $jumlah=$get_data->count();
            if($jumlah > 0){
                $status=true;
                $x=0;
                foreach($get_data as $list_data){
                    $data['bundle_jawaban'][$x]['token_bundle']=Hashids::encode($list_data['id']);
                    $data['bundle_jawaban'][$x]['jawaban']=$list_data['jawaban_text'];
                    $data['bundle_jawaban'][$x]['point']=$list_data['point_jawaban'];
                    $data['bundle_jawaban'][$x]['active']=$list_data['active'] === 1 ? "Y" : "N";
                    $x++;
                }
                $data['bundle_code']=$list_data['bundle_code'];
                $data['bundle_name']=$list_data['bundle_name'];

                $payload=json_encode(['payload'=>$bundle_code]);
                $secret=config('app.hmac_secret');
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

        public function updateBundleJawaban($bundle_code, $nama_bundle, $id_bundle, $jawaban_text, $point, $new_bundle){
            $update=false;
            $get_data=Tref_jawaban_bundle::where('bundle_code', $bundle_code)->get();
            $check_data=Tref_jawaban_bundle::select('bundle_name', 'bundle_code')
                                ->distinct()
                                ->get();
            $check_bundle_name=true;
            foreach($check_data as $list_check_data){
                if($list_check_data['bundle_name'] === $nama_bundle && $bundle_code !== $list_check_data['bundle_code']){
                    $check_bundle_name=false;
                }
            }
            if($check_bundle_name){
                $jlh_jawaban=$get_data->count();
                if($jlh_jawaban > 0){
                    $id_existed_jawaban=[];
                    foreach($get_data as $list_data){
                        $id_existed_jawaban[]=$list_data['id'];
                    }
                    $data_insert=[];
                    $data_update=[];
                    $data_delete=[];
                    
                    $jlh_jawaban_baru=count($id_bundle);
                    $lookup=array_flip($id_existed_jawaban);
                    for($x=0;$x<$jlh_jawaban_baru;$x++){
                        if(!isset($lookup[$id_bundle[$x]])){
                            $data_insert[]=[
                                'bundle_code'=>$bundle_code,
                                'bundle_name'=>$nama_bundle,
                                'jawaban_text'=>$jawaban_text[$x],
                                'point_jawaban'=>$point[$x],
                                'active'=>true
                            ];
                        }else{
                            $data_update[]=[
                                'id_bundle'=>$id_bundle[$x],
                                'jawaban_text'=>$jawaban_text[$x],
                                'point_jawaban'=>$point[$x]
                            ];
                        }
                    }

                    $lookup_input=array_flip($id_bundle);
                    for($y=0;$y<$jlh_jawaban;$y++){
                        if(!isset($lookup_input[$id_existed_jawaban[$y]])){
                            $data_delete[]=$id_existed_jawaban[$y];
                        }
                    }
                    if(count($data_insert) === $new_bundle){
                        try{
                            DB::beginTransaction();
                                DB::table('tref_jawaban_bundle')->insert($data_insert);
                                $jlh_data_update=count($data_update);
                                for($x=0;$x<$jlh_data_update;$x++){
                                    $get_data_update=Tref_jawaban_bundle::where('id', $data_update[$x]['id_bundle'])->first();
                                    $get_data_update->jawaban_text=$data_update[$x]['jawaban_text'];
                                    $get_data_update->point_jawaban=$data_update[$x]['point_jawaban'];
                                    $get_data_update->update();
                                }
                                $update_bundle_name=Tref_jawaban_bundle::where('bundle_code', $bundle_code)->update(['bundle_name'=>$nama_bundle]);
                                $delete_bundle=Tref_jawaban_bundle::whereIn('id', $data_delete)->update(['active'=>false]);
                            DB::commit();
                            $update=true;
                            $msg="Berhasil mengubah data Jawaban Bundle";
                        }catch(\Exception $e){
                            DB::rollBack();
                            $msg=$e->getMessage();
                        }
                    }else{
                        $msg="Data tidak konsisten ";
                    }
                }else{
                    $msg="Data Jawaban Bundle tidak ditemukan";
                }
            }else{
                $msg="Nama Bundle sudah ada";
            }

            return [
                'status'=>$update,
                'msg'=>$msg,
            ];
        }

        public function getListPertanyaan($page){
            $data=[];
            $limit = 10;
            $total=Tref_pertanyaan::where('active', true)->count();
            $jumlahHalaman=ceil($total/$limit);
            $skip=$page * $limit - $limit;
            if($page > $jumlahHalaman){
                $page = 1;
            }
            $bundle_jawaban=Tref_jawaban_bundle::select('bundle_code', 'bundle_name')->distinct()->where('active', true);
            $get_data=Tref_pertanyaan::join('variable_pertanyaan as vp', function($join){
                                       $join->on('vp.id', '=', 'tref_pertanyaan.id_variable')
                                            ->where('vp.active', true);
                                    })
                                ->joinSub($bundle_jawaban, 'b',function($join){
                                    $join->on('b.bundle_code', '=','tref_pertanyaan.bundle_code_jawaban');
                                })
                                ->select('tref_pertanyaan.*', 'vp.variable', 'vp.kriteria', 'b.bundle_name')
                                ->where('tref_pertanyaan.active', true)
                                ->skip($skip)->take($limit)
                                ->get();
            $jlh_data=$get_data->count();
            if($jlh_data > 0){
                $x=0;
                $id_variable_before=null;
                foreach($get_data as $list_data){
                    if($id_variable_before !== $list_data['id_variable']){
                        $a=0;
                        if(!is_null($id_variable_before)){
                            $x++;
                        }
                        $data[$x]['variable']=$list_data['variable'];
                        $data[$x]['kriteria']=$list_data['kriteria'];
                        $data[$x]['pertanyaan']=[];
                    }
                    $data[$x]['pertanyaan'][$a]['token_pertanyaan']=Hashids::encode($list_data['id']);
                   
                    $data[$x]['pertanyaan'][$a]['pertanyaan']=$list_data['pertanyaan'];
                    $data[$x]['pertanyaan'][$a]['bobot']=$list_data['bobot'];
                    $data[$x]['pertanyaan'][$a]['bundle_name_jawaban']=$list_data['bundle_name'];

                    $id_variable_before=$list_data['id_variable'];
                    $a++;
                }
            }

            return [
                'total'=>$total,
                'jumlah_halaman'=>$jumlahHalaman,
                'page'=>$page,
                'data'=>$data
            ];
        }

        public function savePertanyaan($id_variable, $pertanyaan, $bundle_code, $bobot){
            $status=false;
            $get_bobot=Tref_pertanyaan::selectRaw('SUM(bobot) as total_bobot')->where('active', true)->first();
            $total_bobot=$get_bobot['total_bobot'] + (int)$bobot;
            if($total_bobot <= 100){
                $get_data_variable=Variable_pertanyaan::where('id', $id_variable)->where('active', true)->first();
                $bundle_jawaban=Tref_jawaban_bundle::where('bundle_code', $bundle_code)->where('active', true)->first();
            
                if(!is_null($get_data_variable) && !is_null($bundle_jawaban)){
                    $new_pertanyaan=new Tref_pertanyaan;
                    $new_pertanyaan->id_variable=$id_variable;
                    $new_pertanyaan->pertanyaan=$pertanyaan;
                    $new_pertanyaan->bundle_code_jawaban=$bundle_code;
                    $new_pertanyaan->bobot=$bobot;
                    if($new_pertanyaan->save()){
                        $status=true;
                        $msg="Berhasil menyimpan pertanyaan";
                    }
                }else{
                    $msg="Data Variable Pertanyaan atau Bundle Jawaban tidak ditemukan ".$id_variable." : ".$bundle_code;
                }
            }else{
                $msg="Total Bobot Melebihi 100%";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getPertanyaanDetil($pertanyaan_id){
            $signature="";
            $status=false;
            $msg="";
            $data=[];
            $get_data=Tref_pertanyaan::where('id', $pertanyaan_id)->where('active', true)->first();
            if(!is_null($get_data)){
                $data['token_pertanyaan']=Hashids::encode($get_data['id']);
                $data['id_variable']=Hashids::encode($get_data['id_variable']);
                $data['pertanyaan']=$get_data['pertanyaan'];
                $data['bundle_code_jawaban']=$get_data['bundle_code_jawaban'];
                $data['bobot']=$get_data['bobot'];
                $data['active']=$get_data['bobot'] === 1 ? "Y" : "N";

                $payload=json_encode(['payload'=>Hashids::encode($get_data['id'])]);
                $secret=config('app.hmac_secret');
                $signature=hash_hmac('sha256', $payload, $secret);
                $status=true;
            }else{
                $msg="Data Pertanyaan tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data
            ];
        }

        public function updatePertanyaan($id_pertanyaan, $id_variable, $pertanyaan, $active, $bobot, $bundle_code){
            $update=false;
            $get_total=Tref_pertanyaan::selectRaw('SUM(bobot) as bobot')->where('active', true)->first();
            $total_bobot=$get_total['bobot'];
            $get_data=Tref_pertanyaan::where('id', $id_pertanyaan)->first();
            $get_jawaban_bundle=Tref_jawaban_bundle::where('bundle_code', $bundle_code);
            if(!is_null($get_data) || !is_null($get_jawaban_bundle)){
                $selected_bobot=(int)$get_data['bobot'];
                $sisa_bobot=$total_bobot - $selected_bobot;
                $total_bobot_all=$sisa_bobot + (int)$bobot; 
                if($total_bobot_all <= 100){
                    $get_data->id_variable=$id_variable;
                    $get_data->pertanyaan=$pertanyaan;
                    $get_data->bundle_code_jawaban=$bundle_code;
                    $get_data->bobot=$bobot;
                    $get_data->active=$active === "Y" ? 1 : 0;
                    if($get_data->update()){
                        $update=true;
                        $msg="Berhasil mengubah data";
                    }else{
                        $msg="Terjadi kesalahan sistem saat mengubah data";
                    }
                }else{
                    $msg="Total Bobot melebihi 100";
                }

            }else{
                $msg="Data tidak ditemukan ";
            }

            return [
                'status'=>$update,
                'msg'=>$msg
            ];
        }

    }

?>