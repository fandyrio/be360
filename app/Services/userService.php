<?php
    namespace App\Services;

    use App\Models\Tref_users;
    use App\Models\Satker;
use App\Models\Trans_token_wa;
use App\Models\Tref_pegawai;
use App\Models\Tref_sys_config;
use Illuminate\Support\Facades\Hash;
    use Vinkla\Hashids\Facades\Hashids;
    use Illuminate\Support\Facades\DB;
    use Carbon\Carbon;

    class userService{
        public function isSuperAdmin($user_id){
            $get_user=Tref_users::join('idtref_roles', function($join){
                                    $join->on('idtref_roles.IdRole', '=', 'tref_users.IdRole')
                                    ->where('tref_users.IdRole', 1);
                                })
                                ->where('IdUser', $user_id)
                                ->where('tref_users.is_active', true)
                                ->first();
            if(!is_null($get_user)){
                return true;
            }
            return false;
        }

        public function isAdminBadilum($user_id){
            $get_user=Tref_users::join('idtref_roles', function($join){
                                    $join->on('idtref_roles.IdRole', '=', 'tref_users.IdRole')
                                    ->where('tref_users.IdRole', 2);
                                })
                                ->join('v_satker', function($join){
                                    $join->on('v_satker.IdSatker', '=', "tref_users.IdSatker")
                                    ->where('tref_users.IdSatker', 3);   
                                })
                                ->where('IdUser', $user_id)
                                ->where('tref_users.is_active', true)
                                ->first();
            if(!is_null($get_user)){
                return true;
            }
            return false;       
        }

        public function isAdminSatker($user_id){
            $get_user=Tref_users::join('idtref_roles', function($join){
                                    $join->on('idtref_roles.IdRole', '=', 'tref_users.IdRole')
                                    ->where('tref_users.IdRole', 3);
                                })
                                ->join('v_satker', 'v_satker.IdSatker', '=', 'tref_users.IdSatker')
                                ->where('IdUser', $user_id)
                                ->where('tref_users.is_active', true)
                                ->first();
            if(!is_null($get_user)){
                return true;
            }

            return false;
        }

        public function getAllDataUser($page, $isSuperAdmin){
            $data=array();
            $limit=30;
            $total=Tref_users::count();
            $jumlahHalaman=ceil($total / $limit);
            if($page <= 0 || $page > $jumlahHalaman || is_null($page)){
                $page = 1;
            }
            $skip=$page * $limit - $limit;
            if($total > 0){
                $get_data=Tref_users::leftJoin('v_satker', 'v_satker.IdSatker', '=', 'tref_users.IdSatker')
                                ->join('idtref_roles', 'idtref_roles.IdRole', '=', 'tref_users.IdRole')
                                ->select('tref_users.*', 'v_satker.NamaSatker', 'idtref_roles.rolename')
                                ->skip($skip)->take($limit)->get();
                $x=0;
                foreach($get_data as $list_data){
                    $data[$x]['user_enc']=Hashids::encode($list_data['IdUser']);
                    $data[$x]['username']=$list_data['uname'];
                    $data[$x]['rolename']=$list_data['rolename'];
                    $data[$x]['nama']=$list_data['NamaLengkap'];
                    $data[$x]['nip']=$list_data['NIPBaru'];
                    $data[$x]['satker']=$list_data['NamaSatker'];
                    $x++;
                    
                    if(!$isSuperAdmin && $list_data['IdRole'] === 1){
                        $x-=1;
                        $total -= 1;
                    }
                    
                }
            }

            return ['page'=>$page, 'start_number'=> $skip+1, 'jumlah_halaman'=>$jumlahHalaman, 'total'=>$total, 'data'=>$data];
        }

        public function getSatkerBanding(){
            $get_data=Satker::where('ParentIdSatker', 3)->get();
            $total=$get_data->count();
            $x=0;
            foreach($get_data as $list_data){
                $data[$x]['satker_id']=$list_data['IdSatker'];
                $data[$x]['nama_satker']=$list_data['NamaSatker'];
                $data[$x]['kode_satker']=$list_data['KodeSatker'];
                $x++;
            }
            return [
                'total'=>$total,
                'data'=>$data
            ];
        }

        public function getSatkerTKPertama($id_satker_banding){
            $get_data=Satker::where('ParentIdSatker', $id_satker_banding)->get();
            $total=$get_data->count();
            $x=0;
            foreach($get_data as $list_data){
                $data[$x]['satker_id']=$list_data['IdSatker'];
                $data[$x]['nama_satker']=$list_data['NamaSatker'];
                $data[$x]['kode_satker']=$list_data['KodeSatker'];
                $x++;
            }
            return [
                'total'=>$total,
                'data'=>$data
            ];
        }

        public function saveUserDefault($request){
            $save=false;
            $check_user=Tref_users::where('uname', $request->username)->first();
            if(is_null($check_user)){
                $check_satker=Satker::where('KodeSatker', $request->username)->first();
                if(!is_null($check_satker)){
                    $parent_id=(int)$check_satker['ParentIdSatker'];
                    $user=new Tref_users;
                    $user->uname=$request->username;
                    $user->IdPegawai=0;
                    $user->NamaLengkap=$parent_id === 3 ? "Admin Pengadilan Tinggi" : "Admin Pengadilan Negeri";
                    $user->NIPBaru="-";
                    $user->IdRole=$parent_id === 3 ? 3 : 4 ;
                    $user->Passwd=Hash::make($request->username);
                    $user->PasswdTemp_activation=true;
                    $user->IdNamaJabatan=0;
                    $user->IdSatker=$check_satker['IdSatker'];
                    $user->diinput_tgl=date('Y-m-d H:i:s');
                    $user->diinput_oleh=$request->user()->uname;
                    $user->diperbarui_tgl=null;
                    $user->diperbarui_oleh=null;
                    $user->is_active=true;
                    $user->Email="system@user";
                    if($user->save()){
                        $save=true;
                        $msg="Berhasil menyimpan data user";
                    }else{
                        $msg="Terjadi kesalahan sistem saat menyimpan data";
                    }
                }else{
                    $msg="Data Satuan Kerja tidak ditemukan";
                }
            }else{
                $msg="Data user telah ada";
            }

            return [
                'status'=>$save,
                'msg'=>$msg
            ];
        }

        public function getUserById($user_id, $user_id_logged){
            $status=false;
            $data=[];
            $msg="";
            $access=true;
            $get_data=Tref_users::join('tref_roles', 'tref_roles.IdRole', '=', 'tref_users.IdRole')
                            ->leftJoin('v_satker', 'v_satker.IdSatker', '=', 'tref_users.IdSatker')
                            ->select('tref_users.IdUser', 'tref_users.uname', 'tref_users.NamaLengkap', 'tref_users.NIPBaru', 'tref_users.IdPegawai', 'tref_users.IdSatker', 'tref_roles.rolename', 'v_satker.NamaSatker', 'tref_users.IdRole', 'tref_users.is_active')
                            ->where('IdUser', $user_id)->first();
            if(!is_null($get_data)){
                $selected_user_role=(int)$get_data['IdRole'];
                if($selected_user_role === 1){
                    $access=false;
                    if($this->isSuperAdmin($user_id_logged) === true){
                        $access=true;
                    }
                }

                if($access === true){
                    $status=true;
                    $data['user_enc']=Hashids::encode($get_data['IdUser']);
                    $data['username']=$get_data['uname'];
                    $data['nama_lengkap']=$get_data['NamaLengkap'];
                    $data['nip']=$get_data['NIPBaru'];
                    $data['pegawai_id']=$get_data['IdPegawai'];
                    $data['satker_id']=$get_data['IdSatker'];
                    $data['nama_satker']=$get_data['NamaSatker'];
                    $data['rolename']=$get_data['rolename'];
                    $data['is_active']=$get_data['is_active'];
                }else{
                    $msg="Akses ke data ini dibatasi";
                }
            }else{
                $msg="Data user tidak ditemukan ";
            }
            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
        }

        public function updateDataUser($request, $user_id, $user_id_logged){
            $update=false;
            $access=true;
            $get_data=Tref_users::where('IdUser', $user_id)->first();
            if(!is_null($get_data)){
                $selected_role=(int)$get_data['IdRole'];
                if($selected_role === 1){
                    $access=false;
                    if($this->isSuperAdmin($user_id_logged)){
                        $access=true;
                    }
                }

                if($access === true){
                    $password=$request->password;
                    $confirm_password=$request->password_confirmation;
                    if($password === $confirm_password){
                        $get_data->passwd=Hash::make($password);
                        $get_data->is_active=$request->active === "Y" ? true : false;
                        $get_data->diperbarui_tgl=date('Y-m-d H:i:s');
                        $get_data->diperbarui_oleh=$request->user()->uname;
                        $update=$get_data->update();
                        if($update){
                            $msg="Berhasil mengubah data";
                        }else{
                            $msg="Terjadi kesalahan sistem saat mengubah data";
                        }
                    }else{
                        $msg="Password tidak sama";
                    }
                }else{
                    $msg="Tidak dapat melakukan perubahan data ini";
                }
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$update,
                'msg'=>$msg
            ];
        }


        public function getDataUserSatker($username){
            $status=false;
            $msg="";
            $data=[];
            $signature="";
            $get_data=Tref_users::join('v_satker as vs', 'vs.IdSatker', 'tref_users.IdSatker')
                        ->leftJoin('tref_pegawai as tp', 'tp.id_pegawai', '=', 'tref_users.IdPegawai')
                        ->select('tref_users.*', 'vs.NamaSatker', 'tp.foto_pegawai', 'tp.nip', 'tp.no_hp')
                        ->where('uname', $username)->first();
            if(!is_null($get_data)){
                $status=true;
                $data['token_user']=Hashids::encode($get_data['IdUser']);
                $data['username']=$get_data['uname'];
                $data['nama']=$get_data['NamaLengkap'];
                $data['nip']=$get_data['NIPBaru'];
                $data['nama_satker']=$get_data['NamaSatker'];
                $data['email']=$get_data['email'];
                $data['jabatan']=$get_data['nama_jabatan'];
                $data['no_hp']=$get_data['no_hp'];
                $data['foto_pegawai']=$get_data['foto_pegawai'];

                $payload=json_encode(['payload'=>Hashids::encode($get_data['IdUser'])]);
                $secret=config('app.hmac_secret');
            $signature=hash_hmac('sha256', $payload, $secret);

            }else{
                $msg="Data Admin tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data
            ];
        }

        public function getAdminSatkerByNIP($nip, $id_satker){
            $status=false;
            $data=[];
            $get_data=DB::select("CALL SPGetPegawaiNIP('$nip', '$id_satker')");
            $jumlah=count($get_data);
            if($jumlah === 1){
                $status=true;
                $msg="Data Found";
                $get_data=$get_data[0];
                $id_pegawai=$get_data->IdPegawai;
                // $check_data=Tref_pegawai::where('id_pegawai', $id_pegawai)->first();
                $data['token_pegawai']=Hashids::encode($id_pegawai);
                $data['nama']= $get_data->NamaLengkap;
                $data['nip']=$get_data->NIPBaru;
                $data['foto_pegawai']=$get_data->FotoFormal;
                $data['no_hp']=$get_data->NomorHandphone;
                $data['jabatan']=$get_data->NamaJabatan;
                $data['email']=$get_data->EmailPribadi;
            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
        }

        public function sendWaToken($id_satker, $category, $nip, $token_user){
            $expired_at=null;
            $status=false;
            $token=$this->generateToken();
            $msg_wa=getWAMsg($category, $token);
            $get_data=$this->getAdminSatkerByNIP($nip, $id_satker);
            $msg=$get_data['msg'];
            if($get_data['status']){

                $timezone=getZonaWaktuSatker($id_satker);
                if((int)$timezone !== 0){
                    $get_timezone=convertTimeZone($timezone);
                    $expired_at=date('Y-m-d H:i:s', strtotime("+5 minutes", strtotime($get_timezone)));
                }else{
                    $expired_at=date('Y-m-d H:i:s', strtotime("+5 minutes", strtotime(date('Y-m-d H:i:s'))));
                }
                Trans_token_wa::where('id_satker', $id_satker)
                            ->where('category', $category)
                            ->update(['active' => false]);
                $payload=Hashids::encode($id_satker)."-".Hashids::encode($token_user)."-".$category;
                $new_token=new Trans_token_wa;
                $new_token->id_satker=$id_satker;
                $new_token->category=$category;
                $new_token->payload=$get_data['data']['token_pegawai']."-".$payload;
                $new_token->token=$token;
                $new_token->expired_at=$expired_at;
                $new_token->status=false;
                if($new_token->save()){
                    $no_hp=$get_data['data']['no_hp'];
                    // $no_hp="087822507250";
                    $nip=$get_data['data']['nip'];
                    $nama=$get_data['data']['nama'];
                    $send_wa=sendWa($msg_wa, $nip, $nama, $no_hp);
                    $status=$send_wa['status'];
                    $msg=$send_wa['msg'];
                }else{
                    $msg="Terjadi kesalahan sistem saat menyimpan data";
                }
            }

            return [
                'status'=>$status,
                'expired_at'=>$expired_at,
                'msg'=>$msg
            ];
        }

        

        public function generateToken(){
            $get_token=DB::table('trans_token_wa')->pluck('token')->toArray();
            do{
                $kode=substr(time(). random_int(100, 999), -6);
            }while(in_array($kode, $get_token));

            return $kode;
        }

        public function confirmToken($id_satker, $category, $token, $id_user){
            $status=false;
            $get_data=Trans_token_wa::where('id_satker', $id_satker)
                        ->where('category',  $category)
                        ->where('token', $token)
                        ->where('active', true)
                        ->where('status', false)
                        ->first();
            if(!is_null($get_data)){
                $exp_payload=explode("-", $get_data['payload']);
                if(isset($exp_payload[2])){
                    $token_user_enc=$exp_payload[2];
                    $id_satker_enc=$exp_payload[1];

                    $id_user_dec=Hashids::decode($token_user_enc);
                    $id_satker_dec=Hashids::decode($id_satker_enc);

                    if(empty($id_user_dec) || empty($id_satker_dec)){
                        return response()->json(['status'=>false, 'msg'=>"Id User tidak ditemukan"]);
                    }

                    if((int)$id_user_dec[0] === (int)$id_user && (int)$id_satker === (int)$id_satker_dec[0]){
                        $get_data_token=Tref_users::where('IdUser', $id_user_dec[0])
                                    ->where('IdSatker', $id_satker_dec[0])
                                    ->first();
                        if(!is_null($get_data_token)){
                            $time_expired=Carbon::parse($get_data['expired_at']);
                            $date_now=now();

                            $selisih_detil=$date_now->diffInSeconds($time_expired, false);
                            if($selisih_detil > 0){
                                $status=true;
                                $msg="Konfirmasi Berhasil";
                                Trans_token_wa::where('id', $get_data['id'])->update(['active'=>false, 'status'=>true]);
                            }else{
                                Trans_token_wa::where('id', $get_data['id'])->update(['active'=>false]);
                                $msg="Waktu sudah habis";
                            }
                        }else{
                            $msg="Data User tidak ditemukan";
                        }
                    }else{
                        $msg="Data tidak Valid. Silahkan Input data yang benar";
                    }
                }else{
                    $msg="Token Payload tidak ditemukan";
                }
            }else{
                $msg="Token tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];

        }

        public function saveAdmin($nip, $id_satker, $id_user, $password){
            $status=false;
            $get_data=DB::select("CALL SPGetPegawaiNIP('$nip', '$id_satker')");
            $jumlah=count($get_data);
            if($jumlah === 1){
                $data=$get_data[0];
                $id_pegawai=$data->IdPegawai;
                $check_pegawai=Tref_pegawai::where('id_pegawai', $id_pegawai)->first();
                if(is_null($check_pegawai)){
                    try{
                        DB::beginTransaction();
                            $pegawai=new Tref_pegawai;
                            $pegawai->id_pegawai=$id_pegawai;
                            $pegawai->nama_pegawai=$data->NamaLengkap;
                            $pegawai->nip=$data->NIPBaru;
                            $pegawai->no_hp=$data->NomorHandphone;
                            $pegawai->foto_pegawai=$data->FotoFormal;
                            $pegawai->status_pegawai=$data->StatusPegawai;
                            $pegawai->created_at=date('Y-m-d H:i:s');
                            $pegawai->save();
                            $get_user=Tref_users::where('IdUser', $id_user)->first();
                            $get_user->IdPegawai=$id_pegawai;
                            $get_user->NamaLengkap=$data->NamaLengkap;
                            $get_user->NIPBaru=$data->NIPBaru;
                            $get_user->email=$data->EmailPribadi;
                            $get_user->passwd=Hash::make($password);
                            $get_user->last_reset=date("Y-m-d H:i:s");
                            $get_user->IdNamaJabatan=$data->IdNamaJabatan;
                            $get_user->nama_jabatan=$data->NamaJabatan;
                            $get_user->diperbarui_oleh=$get_user->uname;
                            $get_user->diperbarui_tgl=date('Y-m-d H:i:s');
                            $get_user->update();
                        DB::commit();
                        $status=true;
                        $msg="Berhasil menyimpan data Admin Baru";
                    }catch(\Exception $e){
                        DB::rollBack();
                        $msg=$e->getMessage();
                    }
                }else{
                    $get_user=Tref_users::where('IdUser', $id_user)->first();
                    $get_user->IdPegawai=$id_pegawai;
                    $get_user->NamaLengkap=$data->NamaLengkap;
                    $get_user->NIPBaru=$data->NIPBaru;
                    $get_user->email=$data->EmailPribadi;
                    $get_user->passwd=Hash::make($password);
                    $get_user->last_reset=date("Y-m-d H:i:s");
                    $get_user->IdNamaJabatan=$data->IdNamaJabatan;
                    $get_user->nama_jabatan=$data->NamaJabatan;
                    $get_user->diperbarui_oleh=$get_user->uname;
                    $get_user->diperbarui_tgl=date('Y-m-d H:i:s');
                    if($get_user->update()){
                        $status=true;
                        $msg="Berhasil menyimpan data Admin";
                    }

                }
            }else{
                $msg="Data Pegawai tidak ditemukan. Mohon Mengirimkan data yang benar";
            }
            

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function generateUserAdminSatker(){
            $status=false;
            $get_all_satker=Satker::whereRaw('IdSatker > 3')
                        ->get();
            $id_satker=[];
            $kode_satker=[];
            $parent_id=[];
            foreach($get_all_satker as $list_all_satker){
                $id_satker[]=$list_all_satker['IdSatker'];
                $kode_satker[]=$list_all_satker['KodeSatker'];
                $parent_id[]=$list_all_satker['ParentIdSatker'];
            }

            $get_existed_user_satker=Tref_users::whereRaw('IdRole > 2')->get();
            $existed_id_satker=[];
            $existed_kode_satker=[];
            foreach($get_existed_user_satker as $list_satker){
                $existed_id_satker[]=$list_satker['IdSatker'];
                $existed_kode_satker[]=$list_satker['uname'];
            }

            $lookup=array_flip($existed_id_satker);
            $jlh_all_satker=count($id_satker);
            $user_insert=[];
            for($x=0;$x<$jlh_all_satker;$x++){
                if(!isset($lookup[$id_satker[$x]])){
                    $pwd=Hash::make($kode_satker[$x]);
                    $user_insert[]=[
                        'uname'=>$kode_satker[$x],
                        'IdPegawai'=>0,
                        'NamaLengkap'=> $parent_id[$x] === 3 ? "Admin Pengadilan Tinggi" : "Admin Pengadilan Negeri",
                        "NIPBaru" => "-",
                        "IdRole"=> $parent_id[$x] === 3 ? 3 : 4,
                        "email"=> "system@user",
                        "passwd"=>$pwd,
                        "passwdTemp"=>null,
                        "passwdTemp_activation"=>false,
                        "last_reset"=>null,
                        "IdNamaJabatan"=>0,
                        "nama_jabatan"=>null,
                        "IdSatker"=>$id_satker[$x],
                        "diinput_tgl"=>date("Y-m-d H:i:s"),
                        "diinput_oleh"=>"system",
                        "diperbarui_tgl"=>null,
                        "diperbarui_oleh"=>null,
                        "is_active"=>true
                    ];
                }
            }

            $jlh_user_insert=count($user_insert);
            if($jlh_user_insert > 0){
                if(DB::table("tref_users")->insert($user_insert)){
                    $status=true;
                    $msg="Berhasil menginput ".$jlh_user_insert." data Users";
                }else{
                    $msg="Terjadi kesalahan sistem saat menginput data";
                }
            }else{
                $msg="Tidak ada data yang diinput";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

    }

?>