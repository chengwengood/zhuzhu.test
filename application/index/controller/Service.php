<?php

/*

'''''''''''''''''''''''''''''''''''''''''''''''''''''''''

author:ming    contactQQ:811627583

''''''''''''''''''''''''''''''''''''''''''''''''''''''''''

 */

namespace app\index\controller;



use app\common\controller\IndexBase;

use think\Cache;

use think\Controller;

use think\Db;

use My\DataReturn;
use think\Session;


class Service extends IndexBase

{

    public function index()

    {

        return view();

    }

    public function help_center()

    {

        $newslist = Db::name('news')->where('cate',3)->select();

        return view()->assign('newslist',$newslist);

    }

    public function call_center()

    {

        $url_code = Db::name('recharge_mode')->value('server_img');

        return view()->assign('url_code',$url_code);

    }

    //提交工单
    public function question($type=1){
        if(request()->isPOST()){
              $data = input('data/a');
              if(empty($data['mobile'])){
                 echo json_encode(['code'=>0,'msg'=>'请填写手机号']);exit();
              }
              if(empty($data['content'])){
                 echo json_encode(['code'=>0,'msg'=>'请填写描述遇到什么问题']);exit();
              }
              $data['user_id'] = Session::get('user_id');
              $result = Db::name('user')->where(['username'=>$data['mobile'],'id'=>$data['user_id']])->find();
              if(empty($result)){
                 echo json_encode(['code'=>0,'msg'=>'请填写您注册的手机号']);exit();
              }
              
              $data['publish_time'] = time();
              Db::name('question')->insert($data);
              echo json_encode(['code'=>1,'msg'=>'提交成功']);exit();
        }else{
           return view()->assign('type',$type);
        }

    }
    //提交工单
    public function qindex(){
     $list = Db::name('question')->where('user_id',Session::get('user_id'))->select();   
     return view()->assign('list',$list);


    }
}

