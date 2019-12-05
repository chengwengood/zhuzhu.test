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

class Index extends IndexBase
{
    //首页
    public function index()
    {
        $piglist = Db::name('task_config')->select();
        $nowtime = date('H:i');
        $nowday = date('Y-m-d ');
        $time = time();

        foreach ($piglist as $key=>$val) {
            //dump($val);
            //dump($nowtime<$val['start_time'] || $nowtime>$val['end_time']);
            if ($nowtime<$val['start_time']) {
                //echo '测试.....';
                if ($this->isYuyue($val['id'])==1) {
                    $piglist[$key]['game_status'] =2; //待领养
                } else {
                    $piglist[$key]['game_status'] =1; //预约
                }

            }elseif ($nowtime>=$val['start_time'] && $nowtime<=$val['end_time']) {
              //  echo 'open';
                    $nowtime = date('H:i',time()-2*60);
                     if($nowtime<=$val['start_time']){
                         $is_open = Cache::get('is_open'.$val['id']."_".$this->user_id);
                         if(!$is_open || $is_open!=1){
                           $piglist[$key]['game_status'] = 4;//开抢
                         }else{
                           $piglist[$key]['game_status'] = 0;//繁殖中
                         }
                    }else{
                        $piglist[$key]['game_status'] = 0;//繁殖中
                       
                    }

            } elseif($nowtime>$val['end_time']) {
            //echo '00';
                $piglist[$key]['game_status']=0; //繁殖中
            }
        }
        $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));
        //dump($piglist);die;

        $news = Db::name('news')->where(['cate'=>1])->order('id desc')->find();
        $news['content'] = addslashes(htmlspecialchars_decode($news['content']));
        $is_read = Cache::get('news_'.$news['id']."_".$this->user_id);
        return view()->assign(['piglist'=>$piglist,'nowday'=>$nowday,'nowtime'=>$time,'config'=>$config,'news'=>$news,'is_read'=>$is_read]);
    }
    
    public function is_read(){
        $id = input('id');
        Cache::set('news_'.$id."_".$this->user_id,1);
    }


    public function isYuyue($pig_id)
    {
        $map = [];
        $map['uid'] = $this->user_id;
        $map['pig_id'] = $pig_id;
        $map['status'] = 0;
        $insertData['buy_type'] = ['<>', 1];
        $res = Db::name('yuyue')->where($map)->find();
        return $res ? 1 : 0;
    }
    public function checkGame(){
        $game = model('Game');
        $time = $game->excute_time();
        $now_game_time = strtotime($game->gaming_model['start_time']);
        $now_end_time = strtotime($game->gaming_model['end_time']);
        //dump($game);
        //前端显示开奖剩余时间
        $plus_time = $game->excute_time() - $game->openaward;
        //id 游戏ID  time 游戏时间  openaward 开奖冷却时间
        DataReturn::returnJson(200,'',['id'=>$game->game_id,'end_time'=> $now_end_time,'openaward'=>$game->openaward,'cool_time'=>$game->getCoolTime() + 1,'start_time'=>$now_game_time,'stage'=>$game->gameTimeArea($now_game_time)]);

    }
    public function yuyue()
    {
        $data = $this->request->param();
        //dump($data);
        $pig_id = $data['id'];
        $pigInfo =  Db::name('task_config')->where('id',$pig_id)->find();
        $nowTime = date('H:i');
        if ($nowTime>$pigInfo['start_time'] && $nowTime<$pigInfo['end_time'])
        {
            $this->error('不是预约时间');
        }
        //是否实名通过
        $authMap = [];
        $authMap['uid'] = $this->user_id;
        $authMap['status'] = 1;
        if (!Db::name('identity_auth')->where($authMap)->find()) $this->error('请先实名');

        $hasYuyue = $this->isYuyue($pig_id);
        if ($hasYuyue) $this->error('已预约');
        //微分

        if ($this->user['pay_points']<$pigInfo['pay_points']){
            $this->error('微分不足,请充值');
        }

        $baseConfig = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        $userPigsCount = Db::name('pig_order')->where(['uid'=>$this->user_id])->order('id','desc')->count();
        if (isset($baseConfig['qiangdan_limit'])&&$userPigsCount>=$baseConfig['qiangdan_limit']){
            $this->error('您库存的宠物超过最大限额，请等待成熟转让后再来抢哦');
        }

        $insertData = [];

        $insertData['uid'] = $this->user_id;
        $insertData['pig_id'] = $pig_id;
        $insertData['create_time'] = time();
        $insertData['user_sort'] = $this->user['trade_order'];
        $insertData['credit_score'] = $this->user['credit_score'];
        $insertData['buy_type'] = 0;

        $insertData['pay_points'] = $pigInfo['pay_points'];
        $re = Db::name('yuyue')->insert($insertData);
        if ($re) {
            //减少微分
            moneyLog($this->user_id,0,'pay_points',-$pigInfo['pay_points'],3,'预约宠物');
            $this->success('预约成功');

        }else {
            $this->error('预约失败');
        }

    }
    public function yuyueStatus()
    {
        $id = $this->request->param('id');
        $map = [];
        $map['uid'] = $this->user_id;
        $map['pig_id'] = $id;
        $map['status'] = 0;
        $res = Db::name('yuyue')->where($map)->find();

        $code = $res ? 1 : 0;
        return json(['code'=>$code]);

    }
    public function checkOpen()
    {
       $id = $this->request->param('id');
//         dump(Cache::get('is_open'.$id));
        //dump($id);
        $is_open = Cache::get('is_open'.$id);
        //dump(Cache::clear());die;
        if (!$is_open) {
            return json(['code'=>0,'msg'=>'未开奖']);
        } else {
            $luckyUsers = Cache::get('buy_'.date('Ymd').$id);
            //dump($luckyUsers);die;

            $uid = $this->user_id;
            if (!empty($luckyUsers)) {
                if (in_array($uid,$luckyUsers)) {
                    return json(['code'=>1,'msg'=>'恭喜']);
                } else {
                    return json(['code'=>2,'msg'=>'很遗憾']);
                }
            }else{
                return json(['code'=>2,'msg'=>'很遗憾']);
            }

        }

    }


    public function flash_buy()
    {
        $data = $this->request->param();
        //dump($data);
        $pig_id = $data['id'];
        $pigInfo =  Db::name('task_config')->where('id',$pig_id)->find();
        $nowTime = date('H:i');
        if ($nowTime<$pigInfo['start_time'] || $nowTime>$pigInfo['end_time'])
        {
            $this->error('不是开抢时间');
        }
        //是否实名通过
        $authMap = [];
        $authMap['uid'] = $this->user_id;
        $authMap['status'] = 1;
        if (!Db::name('identity_auth')->where($authMap)->find()) $this->error('请先实名');


        $baseConfig = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        $userPigsCount = Db::name('pig_order')->where(['uid'=>$this->user_id])->order('id','desc')->count();
        if (isset($baseConfig['qiangdan_limit'])&&$userPigsCount>=$baseConfig['qiangdan_limit']){
            $this->error('您库存的宠物超过最大限额，请等待成熟转让后再来抢哦');
        }


        $today = strtotime(date('Y-m-d'));
        $map = [];
        $map['uid'] = $this->user_id;
        $map['pig_id'] = $pig_id;
        $map['status'] = 1;
        $map['create_time'] = ['gt',$today];
        $alread_res = Db::name('yuyue')->where($map)->find();
        if($alread_res){
            $this->error('您今天已经抢到一个'.$pigInfo['name'].'了，明天再来哦');
        }

        $map = [];
        $map['uid'] = $this->user_id;
        $map['pig_id'] = $pig_id;
        $map['status'] = 0;
        $insertData['buy_type'] = 1;
        $res = Db::name('yuyue')->where($map)->find();

        if(!$res){
            //微分
            if ($this->user['pay_points']<$pigInfo['qiang_points']){
                $this->error('微分不足,请充值');
            }

            $insertData = [];

            $insertData['uid'] = $this->user_id;
            $insertData['pig_id'] = $pig_id;
            $insertData['create_time'] = time();
            $insertData['user_sort'] = $this->user['trade_order'];
            $insertData['credit_score'] = $this->user['credit_score'];
            $insertData['buy_type'] = 1;

            $insertData['pay_points'] = $pigInfo['qiang_points'];
            $re = Db::name('yuyue')->insert($insertData);
            if ($re) {
                //减少微分
                moneyLog($this->user_id,0,'pay_points',-$pigInfo['qiang_points'],3,'抢购宠物');
                $this->success('进入抢购成功');

            }else {
                $this->error('抢购失败');
            }
        }else if($this->isYuyue($pig_id)){
            $map = [];
            $map['uid'] = $this->user_id;
            $map['pig_id'] = $pig_id;
            $map['status'] = 0;
            $insertData['buy_type'] = ['<>', 1];
            //已经预约的，修改bug_type为2
            $re = Db::name('yuyue')->insert($insertData);
            Db::name('yuyue')->where($map)->update(['buy_type'=>2]);
            $this->success('进入抢购成功');
        }else{
            $this->success('进入抢购成功');
        }
    }

    public function checkFlushOpen()
    {
        $id = $this->request->param('id');
        $config = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        if(isset($config['jk_open'])&&$config['jk_open']){
            $result = $this->lijiqiang($id);
            if($result==1){
                return json(['code'=>1,'msg'=>'恭喜']);
            }else if($result==2){
                return json(['code'=>2,'msg'=>'很遗憾']);
            }else{
                return json(['code'=>0,'msg'=>'未开奖']);
            }
        }else{
            $endtime = $this->request->param('endtime');
            $uid = $this->user_id;
            $nowTime = date('H:i:s',time()-90);

            $is_open = Cache::get('is_open'.$id);
            if (!$is_open || $nowTime<=$endtime) {
                return json(['code'=>0,'msg'=>'未开奖']);
            } else {
                $luckyUsers = Cache::get('buy_'.date('Ymd'). $id);
                //dump($luckyUsers);die;

                if (!empty($luckyUsers)) {
                    if (in_array($uid,$luckyUsers)) {
                        return json(['code'=>1,'msg'=>'恭喜']);
                    } else {
                        return json(['code'=>2,'msg'=>'很遗憾']);
                    }
                }else{
                    return json(['code'=>2,'msg'=>'很遗憾']);
                }

            }
        }


    }


    public function lijiqiang($id){
        $map['id'] = $id;
        $map['is_open'] = 0;
        $pigInfo = Db::name('task_config')->where($map)->order('start_time')->find();
        if (!$pigInfo) return 0;
        //查找可出售的猪
        $pigMap = [];
        $pigMap['pig_id'] = $pigInfo['id'];
        $pigMap['status'] = 0;
        $piglist = Db::name('pig_order')->where($pigMap)->select();

        //查询预约的人
        $userMap = [];
        $userMap['uid'] = $this->user_id;
        $userMap['pig_id'] = $id;
        $userMap['status'] = 0;
        $userMap['buy_type'] = ['<>', 0]; //buy_type0只预约，1只抢购，2预约加抢购    只预约了，是不能参与的

        $yuyueInfo = Db::name('yuyue')->where($userMap)->find();

        $uid = $this->user_id;
        if (!empty($piglist) && $yuyueInfo) {
            //有卖单
            foreach ($piglist as $val) {
                if($val['uid']==$uid){
                    continue;//不能抢自己的
                }

                //改变订单
                Db::name('pig_order')
                    ->where('id', $val['id'])
                    ->update([
                        'status' => 1,
                        'uid' => $uid,
                        'sell_id' => $val['uid'],
                        'create_time' => time()
                    ]);
                //改变用户猪的状态
                Db::name('user_pigs')->where('id', $val['id'])->setField('status', 3);
                //改变预约状态
                Db::name('yuyue')->where('uid', $uid)->where($userMap)->setField('status', 1);
                return 1;
            }
        }

        //改变预约状态
        Db::name('yuyue')->where('uid', $uid)->where($userMap)->setField('status', 2);

        if($yuyueInfo['buy_type']==1){
            moneyLog($yuyueInfo['uid'], 0, 'pay_points', $yuyueInfo['pay_points'], 4, '抢购未中奖立返');
        }else{
            moneyLog($yuyueInfo['uid'], 0, 'pay_points', $yuyueInfo['pay_points'], 4, '预约未中奖立返');
        }
        return 2;
    }




}
