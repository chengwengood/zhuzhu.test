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
        $piglist = Db::name('task_config')->order("start_time asc")->select();
        $nowtime = date('H:i');
        $nowday = date('Y-m-d ');
        $time = time();


        foreach ($piglist as $key=>$val) {

            if ($nowtime<$val['start_time']) {
                //echo '测试.....';
                if ($this->isYuyue($val['id'])==1) {
                    $piglist[$key]['game_status'] =2; //待领养
                } else {
                    $piglist[$key]['game_status'] =1; //预约
                }
                // var_dump($piglist);
                // die;

            }elseif ($nowtime>=$val['start_time'] && $nowtime<=$val['end_time']) {
                //  echo 'open';
                $nowtime = date('H:i',time()-100);
                if($nowtime<=$val['start_time']){
                    $count=Db::name('pig_order')->where(array('status'=>0,'pig_id'=>$val['id']))->count();
                    if($count >0){  //判断是否有宠物订单
                        $is_open = Cache::get('is_open'.$val['id']."_".$this->user_id);
                        if(!$is_open || $is_open!=1){
                            $piglist[$key]['game_status'] = 4;//开抢
                        }else{
                            $piglist[$key]['game_status'] = 0;//繁殖中
                        }
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

        //var_dump($piglist);
        //die;


        $config=unserialize(Db::name('system')->where('name','site_config')->value('value'));
        //dump($piglist);die;
        $this->is_Active();
        /*  $news = Db::name('news')->where(['cate'=>1])->order('id desc')->find();
          $news['content'] = addslashes(htmlspecialchars_decode($news['content']));
          $is_read = Cache::get('news_'.$news['id']."_".$this->user_id);
  */

        return view()->assign(['piglist'=>$piglist,'nowday'=>$nowday,'nowtime'=>$time,'config'=>$config]);
    }

    //未付款、未确认订单判断
    public function is_payment_order()
    {
        $pig_id = input('pig_id');
        $is_confirm = Cache::get('news32_pig_'.$pig_id.'_'.$this->user_id);
        if($is_confirm){
            $this->error('已经通知过了');
        }
        /*//查询未付款订单
        $map['status'] = 1;
        $map['uid']=$this->user_id;
        $no_payment_list = Db::name('pig_order')->where($map)->find();
        //查询未确认订单
        $where['status']=2;
        $where['sell_id']=$this->user_id;
        $no_confirm_list = Db::name('pig_order')->where($where)->find();
        if (!$no_payment_list&&!$no_confirm_list) $this->error('没有未付款与需确认订单！');*/
        //获得未付款消息模板
        $news = Db::name('news')->find(32);
        //消息内容
        $content_t=$news['content'];
        //简体转化为繁体
        $content=(new \My\T_turn_s())->gb2312_big5($content_t);
        $news['content'] = html_entity_decode($content);
        Cache::set('news32_pig_'.$pig_id.'_'.$this->user_id,1,3600);
        $this->success('请求成功！','',$news);
    }
    //宠物开抢提醒判断
    public function is_inform()
    {

        $pig_id = input('pig_id');
        $is_infrom = Cache::get('news_pig_'.$pig_id.'_'.$this->user_id);
        if($is_infrom){
            $this->error('已经通知过了');
        }
        $piglist = Db::name('task_config')->order("start_time asc")->select();
        foreach($piglist as $k=>$v){
            if($v['id']==$pig_id){
                $arr=['一','二','三','四','五','六','七','八','九','十','十一','十二','十三'];
                $order=$arr[$k];
                $pigInfo=$v;
                break;
            }
        }
        if(empty($order)||empty($pigInfo)){
            $this->error('输入参数有误！');
        }
        //$pigInfo = Db::name('task_config')->find($pig_id);
        //每只宠物月利润
        $min_revenue=floor($pigInfo['min_price']*$pigInfo['contract_revenue']*30/100);
        $max_revenue=floor($pigInfo['max_price']*$pigInfo['contract_revenue']*30/100);
        //获得消息模板
        $news = Db::name('news')->find(31);
        $content_t=sprintf($news['content'],$order,$pigInfo['start_time'],$pigInfo['name'],$pigInfo['cycle'],floor($pigInfo['contract_revenue']).'%',floor($pigInfo['min_price']),floor($pigInfo['max_price']),$pigInfo['name'],$min_revenue,$max_revenue);
        //简体转化为繁体
        $content=(new \My\T_turn_s())->gb2312_big5($content_t);
        $news['content'] = html_entity_decode($content);
        Cache::set('news_pig_'.$pig_id.'_'.$this->user_id,1,3600);
        $this->success('请求成功！','',$news);
    }


    public function is_read(){
        $id = input('id');
        Cache::set('news_'.$id."_".$this->user_id,1);
    }

    public function is_Active(){
        $uid = $this->user_id;
        $zMap = [];
        $zMap['id'] = $uid;
        $pps = Db::name('user')->where($zMap)->sum('pay_points');
        $isA = Db::name('user')->where($zMap)->sum('is_Active');
        if($pps>'50'&&$isA==0){
            Db::name('user')->where($zMap)->update(['is_Active'=>'1']);
        }
    }


    public function isYuyue($pig_id)
    {
        $map = [];
        $map['uid'] = $this->user_id;
        $map['pig_id'] = $pig_id;
        $map['create_time']=['gt', strtotime(date('Y-m-d'))];
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
        $baseConfig = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        $nowTime = date('H:i');
        if ($nowTime>$pigInfo['start_time'] && $nowTime<$pigInfo['end_time'])
        {
            $this->error('不是预约时间');
        }

        if($pigInfo['start_time']<date('H:i',time()+$baseConfig['jk_open_yuyue'])&&$nowTime<$pigInfo['start_time'] ){
            $this->error('开抢前'.floor($baseConfig['jk_open_yuyue']/60).'分钟内不允许预约');
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
        $this->checkA();
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

    public function checkA(){
        $uid = $this->user_id;
        $zMap = [];
        $zMap['id'] = $uid;
        $isA = Db::name('user')->where($zMap)->sum('is_Active');
        if($isA==0){
            $this->error('账号未激活,请联系上级充值');
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
        $this->checkA();
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
            //sleep($config['jk_open_time']);
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

        if($this->user['is_restrict'] == 1){
            return 2;
        }
        $pigInfo = Db::name('task_config')->where($map)->order('start_time')->find();
        if (!$pigInfo) return 0;
        //查找可出售的猪
        $pigMap = [];
        $pigMap['pig_id'] = $pigInfo['id'];
        $pigMap['status'] = 0;
        $pigMap['point_id']=['eq',0];
        $piglist = Db::name('pig_order')->where($pigMap)->select();

        //查找可出售被指定的猪
        $pigMap['point_id']=['neq',0];
        $piglist1=Db::name('pig_order')->where($pigMap)->select();

        //查询预约的人
        $userMap = [];
        $userMap['uid'] = $this->user_id;
        $userMap['pig_id'] = $id;
        $userMap['status'] = 0;
        $userMap['buy_type'] = ['<>', 0]; //buy_type0只预约，1只抢购，2预约加抢购    只预约了，是不能参与的

        $yuyueInfo = Db::name('yuyue')->where($userMap)->find();

        $uid = $this->user_id;
        if (!empty($piglist1) && $yuyueInfo) {
            //有卖单
            foreach ($piglist1 as $val) {
                if($val['uid']==$uid){
                    continue;//不能抢自己的
                }
                if($val['point_id'] ==$uid){
                    Db::startTrans();
                    //改变订单
                    $pig_order_update=Db::name('pig_order')->where('id', $val['id'])
                        ->update([ 'status' => 1,'uid' => $uid, 'sell_id' => $val['uid'],'create_time' => time()]);
                    //改变用户猪的状态
                    $user_pigs_update=Db::name('user_pigs')->where('order_id', $val['id'])->setField('status', 3);
                    //改变预约状态
                    $yuyue_update=Db::name('yuyue')->where('uid', $uid)->where($userMap)->setField('status', 1);
                    if($pig_order_update&&$user_pigs_update&&$yuyue_update){
                        Db::commit();
                        return 1;
                    }else{
                        Db::rollback();
                        return 2;
                    }

                }else{
                    continue;//不能抢不是标志自己的
                }

            }
        }
        if (!empty($piglist) && $yuyueInfo) {
            //有卖单
            foreach ($piglist as $val) {
                if($val['uid']==$uid){
                    continue;//不能抢自己的
                }
                Db::startTrans();
                //改变订单
                $pig_order_update=Db::name('pig_order')->where('id', $val['id'])
                    ->update([ 'status' => 1,'uid' => $uid, 'sell_id' => $val['uid'],'create_time' => time()]);
                //改变用户猪的状态
                $user_pigs_update=Db::name('user_pigs')->where('order_id', $val['id'])->setField('status', 3);
                //改变预约状态
                $yuyue_update=Db::name('yuyue')->where('uid', $uid)->where($userMap)->setField('status', 1);
                if($pig_order_update&&$user_pigs_update&&$yuyue_update){
                    Db::commit();
                    return 1;
                }else{
                    Db::rollback();
                    return 2;
                }

            }
        }

        //改变预约状态
        Db::name('yuyue')->where('uid', $uid)->where($userMap)->setField('status', 2);

        /* if($yuyueInfo['buy_type']==1){
             moneyLog($yuyueInfo['uid'], 0, 'pay_points', $yuyueInfo['pay_points'], 4, '抢购未中奖立返');
         }else{
             moneyLog($yuyueInfo['uid'], 0, 'pay_points', $yuyueInfo['pay_points'], 4, '预约未中奖立返');
         }*/
        return 2;
    }



}
