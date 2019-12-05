<?php

/*

'''''''''''''''''''''''''''''''''''''''''''''''''''''''''

author:ming    contactQQ:811627583

''''''''''''''''''''''''''''''''''''''''''''''''''''''''''

 */

namespace app\index\controller;



use think\Controller;

use think\Config;

use think\Session;

use think\Db;

class Login extends Controller

{

	//登录

    public function index()

    {

    	if ($this->request->isPost()) {

            $data            = $this->request->post();

            //dump($data);die;

            $validate_result = $this->validate($data['data'],

                'user.login');

            if ($validate_result !== true) {

                $this->error($validate_result);

            }

            $where['mobile'] = $data['data']['mobile'];

            $where['password'] = md5($data['data']['password'] . Config::get('salt'));



            $user = Db::name('user')->field('id,username,mobile,status')->where($where)->find();

            if(empty($user)){

            	$this->error('用户名或密码错误');

            }

            if ($user['status'] != 1) {

                $this->error('当前用户未激活或已禁用');

            }

            Session::set('user_id', $user['id']);

            Db::name('user')->update(

                [

                    'last_login_time' => time(),

                    'last_login_ip'   => $this->request->ip(),

                    'id'              => $user['id']

                ]

            );

            if ($data['data']['remember_pwd']==1) {

                Session::set('login_mobile',$data['data']['mobile']);

                Session::set('login_pwd',$data['data']['password']);

            }

            $this->success('登录成功', url('index/index'));

        }else{

            $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));

            $this->assign('config',$config);

        }

        return $this->fetch();

    }





    //华登登录

    public function hdindex()

    {

        if ($this->request->isPost()) {

            $data            = $this->request->post();

            //dump($data);die;

            $validate_result = $this->validate($data,

                'user.login');

            if ($validate_result !== true) {

                $this->error($validate_result);

            }

            $where['mobile'] = $data['mobile'];

            $where['password'] = md5($data['password'] . Config::get('salt'));



            $user = Db::name('user')->field('id,username,mobile,status')->where($where)->find();

            if(empty($user)){

                $this->error('用户名或密码错误');

            }

            if ($user['status'] != 1) {

                $this->error('当前用户未激活或已禁用');

            }

            Session::set('user_id', $user['id']);

            Db::name('user')->update(

                [

                    'last_login_time' => time(),

                    'last_login_ip'   => $this->request->ip(),

                    'id'              => $user['id']

                ]

            );

            $this->success('登录成功', url('index/index'));

        }else{

            $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));

            $this->assign('config',$config);

        }

        return $this->fetch();

    }





    //华登登录

    public function downapp()

    {

        $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));

        $this->assign('config',$config);

        return $this->fetch();

    }





    //华登登录

    public function qrcode2()

    {

        $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));

        $this->assign('config',$config);

        return $this->fetch();

    }



    public function register(){

        if($this->request->isPost()){



            $data            = $this->request->post();

            //dump($data);die;

            $validate_result = $this->validate($data['data'], 'User.reg');

            if ($validate_result !== true) {

                $this->error($validate_result);

            }

            if (empty($data['data']['mobile_code'])) {

                $this->error('验证码不能为空');

            }

//             if(!$this->check_code($data['username'],$data['code'])){

//                 $this->error('手机验证码错误');

//             }

            $checkCode = model('Check')->checkCode($data['data']['mobile'],$data['data']['mobile_code']);

            if (!$checkCode['state']) {

                $this->error($checkCode['msg']);

            }

            //if (empty($data['xy'])) $this->error('请详细阅读协议，如无异议，请勾选同意！');

//            dump($checkCode);die;

            $user_id=model('user')->reg();

//            dump($user_id);die;

            if($user_id){

                $config = unserialize(Db::name('system')->where('name','base_config')->value('value'));

                $pay_points = $config['pay_points'];

                 //赠送微分

                moneyLog($user_id,0,'pay_points',$pay_points,10,'注册赠送微分');



                $this->success('注册成功！',url('login/downapp'));

                exit;

            }else{

                $this->error('注册失败');

            }

        }

        $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));

        $this->assign('config',$config);

        $id = $this->request->param('pid');

        $pinfo=Db::name('user')->where('id',$id)->find();

        $this->assign('pinfo',$pinfo);

        //dump($pinfo);die;

        //dump($this->request->p)

        return $this->fetch();

    }



    //忘记密码

    public function forget_password()

    {

        if($this->request->isPost()){

            $data      = $this->request->post();

            //dump($data);die;

            empty($data['data']['mobile']) ? $this->error('请输入手机号') : '';

            $id = Db::name('user')->where('mobile',$data['data']['mobile'])->value('id');

            if(!$id){

                $this->error('此手机号没有注册');

            }

//            if(!$this->check_code($data['mobile'],$data['code'])){

//                $this->error('手机验证码错误');

//            }

            $checkCode = model('Check')->checkCode($data['data']['mobile'],$data['data']['code']);

            $checkCode['state'] == 0 ? $this->error($checkCode['msg']) : '';

            if($data['data']['new_password']!=$data['data']['confirm_password']){

                $this->error('两次密码不一致');

            }

            $pwd=md5($data['data']['new_password'] . Config::get('salt'));

            if(Db::name('user')->where('id',$id)->update(['password'=>$pwd])){

                $this->success('密码修改成功',url('login/index'));

            }

        }

       return $this->fetch();

    }



    public function logout(){

    	Session::delete('user_id');

    	$this->success('退出成功',url('index'));

    }




       //验证码验证
    public function get_Captcha()
    {
        
       $captcha = new \think\captcha\Captcha();
        $captcha->fontSize = 30;
        $captcha->length = 4;
        $captcha->useCurve = false;
       $captcha->codeSet = '0123456789'; 
       return $captcha->entry();
    }
       //验证码验证
    public function check_Captcha($code='')
    {
       $captcha = new \think\captcha\Captcha();
       if (!$captcha->check($code)) {
           $this->error('验证码错误');
       } 
       return true;
    }



//发送手机验证码
    public function smsCode(){
        $mobile = $this->request->post('data.mobile');
        $pattern = "/^1[3456789]{1}\d{9}$/";

        //手机号不能为空
        if (empty($mobile) || !preg_match($pattern,$mobile)) {
            $this->error('请输入正确的手机号');
        }
        //测试期
        $code=rand(111111,999999);
        $data['mobile']=$mobile;
        $data['code']=$code;
        $data['sendtime']=time();
        $data['state']=0;
        $data['endtime']=time()+600;
        $sendResult = Db::name('mscode')->insertGetId($data);
        if($sendResult>0){
            $res = $this->sendSms($mobile,$code);
            if($res['error_code']==0){
                Db::name('mscode')->where(['id'=>$sendResult])->update(['state'=>1]);
                $this->success("发送成功，请查看手机");
            }else{
                Db::name('mscode')->where(['id'=>$sendResult])->update(['state'=>2]);
                $this->error($res['reason']);
            }
        }
    }
    function sendSms($mobile,$code) {

        $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));
        if(!isset($config['sms_open'])){
            return false;
        }
        $content = file_get_contents("http://v.juhe.cn/sms/send?mobile={$mobile}&tpl_id=183340&tpl_value=%23code%23%3D{$code}&key=ebce92d4e1ef84acd94a099069909f63");
       // $content = file_get_contents("http://v.juhe.cn/sms/send?mobile={$mobile}&tpl_id=".$config['sms_template']."&tpl_value=%23code%23%3D{$code}&key=".$config['sms_key']);
        $content = json_decode($content,true);
        return $content;
    }

    //验证手机验证码

    public function check_code($mobile,$code)

    {

        if(cache('code_'.$mobile)==$code){

            return true;

        }else{

            return false;

        }

    }

}

