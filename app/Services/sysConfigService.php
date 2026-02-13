<?php
    namespace App\Services;
    use App\Models\Tref_sys_config;
use Vinkla\Hashids\Facades\Hashids;

    class sysConfigService{
        public function __construct()
        {
            // throw new \Exception('Not implemented');
        }

        public function getAllSysConfig(){
            $jumlah_config=0;
            $config=[];
            $get_all=Tref_sys_config::all();
            $jumlah_config=$get_all->count();
            $x=0;
            foreach($get_all as $list_sysConfig){
                $config[$x]['config_name']=$list_sysConfig['config_name'];
                $config[$x]['id']=Hashids::encode($list_sysConfig['id']);
                $config[$x]['value']=$list_sysConfig['config_value_str'];
                $x++;
            }
            return [
                'jumlah'=>$jumlah_config,
                'config'=>$config
            ];
        }
    }
?>