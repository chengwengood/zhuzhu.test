<?php
/*
'''''''''''''''''''''''''''''''''''''''''''''''''''''''''
author:ming    contactQQ:811627583
''''''''''''''''''''''''''''''''''''''''''''''''''''''''''
 */

namespace app\api\controller;

use think\Controller;
use think\Db;
use app\common\model\User;
use think\Cache;
use think\Log;

/**
 * 计划任务
 * Class Index
 * @package app\api\controller
 */
class Plan extends Controller
{


    /**
     * 预约抢购任务
     * @return mixed
     */
    public function test()
    {
//        $config = unserialize(Db::name('system')->where('name','base_config')->value('value'));
//        if(isset($config['jk_open'])&&$config['jk_open']){
//            die;
//        }
        //检测开奖的猪
        $nowtime = date('H:i:s');
        $map = [];
        $map['end_time'] = ['lt', $nowtime];
        $map['is_open'] = 0;
        $pigInfo = Db::name('task_config')->where($map)->order('start_time')->find();
        dump($pigInfo);
        if (!$pigInfo) die;
        dump(Cache::get('opening'));
        if (Cache::get('opening')) die;
        Cache::set('opening',1);
        Cache::set('is_open'.$pigInfo['id'],0);
        //查找可出售的猪
        $pigMap = [];
        $pigMap['pig_id'] = $pigInfo['id'];
        //$pigMap['from_id'] = 0;
        $pigMap['status'] = 0;
        $map['user_sort'] = ['<>', 0];
        $piglist = Db::name('pig_order')->where($pigMap)->select();
        dump($piglist);
        //查询预约的人
        $userMap = [];
        $userMap['pig_id'] = $pigInfo['id'];
        $userMap['status'] = 0;
        $userMap['buy_type'] = ['<>', 0]; //buy_type0只预约，1只抢购，2预约加抢购    只预约了，是不能参与的
        $userList = Db::name('yuyue')->where($userMap)->order('user_sort,credit_score')->select();
        $redisArr = [];
        $redisName = 'buy_'.date('Ymd').$pigInfo['id'];
        if (!empty($piglist)) {
            //有卖单
            foreach ($piglist as $val) {
                //是否有指定
                if ($val['point_id']) {
                    $uid = $val['point_id'];
                } else {
                    $uid = $this->createUserId($val['pig_id'], $val['uid']);
                    if (!$uid) break;

                }
                //改变订单
                Db::name('pig_order')
                    ->where('id', $val['id'])->update(['status' => 1, 'uid' => $uid,'sell_id' => $val['uid'], 'create_time' => time()]);
                //改变用户猪的状态
                Db::name('user_pigs')->where('order_id', $val['id'])->setField('status', 3);
                //改变预约状态
                Db::name('yuyue')->where('uid', $uid)->where($userMap)->setField('status', 1);
                array_push($redisArr, $uid);

            }
        }
        //dump($redisArr);die;
        dump(json_encode($redisArr));
        echo $redisName;
        //写入redis
        Cache::set($redisName,$redisArr,86400);
        //改变猪的状态
        Db::name('task_config')->where('id',$pigInfo['id'])->setField('is_open',1);
        dump(Cache::get($redisName));
        Cache::set('is_open'.$pigInfo['id'],1,3600);
        Cache::rm('opening');
        dump(Cache::get('is_open'.$pigInfo['id']));
        //未抢到的
        $this->pointsBack($pigInfo['id']);
    }


    public function createUserId($pig_id, $sell_id)
    {
        $userMap = [];
        $userMap['pig_id'] = $pig_id;
        $userMap['status'] = 0;
        //所有指定的用户ID
        $pointIds = Db::name('user_pigs')->where('point_id', '<>', 0)->column('point_id');
        array_push($pointIds, $sell_id);
        $userMap['uid'] = ['not in', $pointIds];
        $userMap['user_sort'] = ['<>', 0];
        $userMap['buy_type']  = ['<>', 0];//buy_type0只预约，1只抢购，2预约加抢购    只预约了，是不能参与的
        $yuyue = Db::name('yuyue')->where($userMap)->order('user_sort,credit_score', 'desc')->find();
        return $yuyue['uid'];

    }

    /**
     * @param $pig_id 猪ID
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pointsBack($pig_id)
    {
        $map = [];
        $map['pig_id'] = $pig_id;
        $map['status'] = 0;
        $yuyueList = Db::name('yuyue')->where($map)->select();
        if (!empty($yuyueList)) {
            foreach ($yuyueList as $val) {
                //改变预约状态未中奖
                Db::name('yuyue')->where('id', $val['id'])->setField('status', 2);
                //返回预约积分
                if($val['buy_type']==1){
                    moneyLog($val['uid'], 0, 'pay_points', $val['pay_points'], 4, '抢购未中奖返还');
                }else{
                    moneyLog($val['uid'], 0, 'pay_points', $val['pay_points'], 4, '预约未中奖返还');
                }
            }
        }

    }

    /**
     * @param $pig_id 猪ID
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pointsbackbatch($pig_id)
    {
        $map = [];
        $map['pig_id'] = $pig_id;
        $map['status'] = 2;
        $map['buy_type'] = ['<>', 0];
        $yuyueList = Db::name('yuyue')->where($map)->select();
        if (!empty($yuyueList)) {
            foreach ($yuyueList as $val) {
                //返回预约积分
                if($val['buy_type']==1){
                    moneyLog($val['uid'], 0, 'pay_points', $val['pay_points'], 4, '抢购未中奖返还');
                }else{
                    moneyLog($val['uid'], 0, 'pay_points', $val['pay_points'], 4, '预约未中奖返还');
                }
            }
        }

    }

    /**
     * 重置游戏
     */
    public function resetGames()
    {
        Log::record('[ resetGames ] 开始执行', 'info');
        $pigList = Db::name('task_config')->field('id,is_open')->select();
        foreach ($pigList as $pig) {
            Db::name('task_config')->where('id', $pig['id'])->setField(['is_open'=>0,'is_flush_open'=>0, 'selled_stock'=>0]);
        }
        Log::record('[ resetGames ] 结束执行', 'info');
    }
    //用户收益
    public function userReward()
    {
        Log::record('[ userReward ] 开始执行', 'info');
        $nowTime = time();
        $map = [];
        $map['status'] = 0;
        $map['end_time'] = ['<', $nowTime];
        $pigsList = Db::name('user_pigs')->where($map)->select();
        $config = unserialize(Db::name('system')->where('name', 'base_config')->value('value'));
        dump($pigsList);
        foreach ($pigsList as $key => $val) {
            $pigReward = $this->pigReward($val['pig_id']);
            $contract_revenue = $val['price'] * $pigReward['contract_revenue'] / 100;
            $doge = $val['price'] * $pigReward['doge'] / 100;
            //收益表记录
            $this->addReward($val['uid'], 0, 'contract_revenue', $contract_revenue, 1, '智能合约');
            //累计收益
            Db::name('user')->where('id', $val['uid'])->setInc('totalmoney', $contract_revenue);
            //增加猪的价值
            model('Pig')->pigUpgarde($val['id'], $contract_revenue);
            $this->addReward($val['uid'], 0, 'doge', $doge, 5, 'DOGE收益');
            moneyLog($val['uid'], 0, 'doge', $doge, 6, 'DOGE收益');
            //上级分成
            $parents = $this->threeParents($val['uid']);
            if ($parents['pid'] > 0) {
                $firstReward = $contract_revenue * $config['firt_parent'] / 100;
                $this->addReward($parents['pid'], $val['uid'], 'share_integral', $firstReward, 2, '一代推广');
                //累计奖励
                Db::name('user')->where('id', $parents['pid'])->setInc('total_share_integral');
                //资产记录
                moneyLog($parents['pid'], $val['uid'], 'share_integral', $firstReward, 4, '一代推广收益');
            }
            if ($parents['pid2'] > 0) {
                $secondReward = $contract_revenue * $config['second_parent'] / 100;
                $this->addReward($parents['pid2'], $val['uid'], 'share_integral', $secondReward, 2, '二代推广');
                //累计奖励
                Db::name('user')->where('id', $parents['pid2'])->setInc('total_share_integral');
                //资产记录
                moneyLog($parents['pid'], $val['uid'], 'share_integral', $secondReward, 4, '二代推广收益');
            }

            if ($parents['pid3'] > 0) {
                $thirdReward = $contract_revenue * $config['third_parent'] / 100;
                $this->addReward($parents['pid3'], $val['uid'], 'share_integral', $thirdReward, 2, '三代推广');
                //累计奖励
                Db::name('user')->where('id', $parents['pid3'])->setInc('total_share_integral');
                //资产记录
                moneyLog($parents['pid'], $val['uid'], 'share_integral', $thirdReward, 4, '三代推广收益');
            }

        }
        Log::record('[ userReward ] 结束执行', 'info');

    }
    //团队收益
    public function teamReward()
    {
        $rewardMap = [];
        $rewardMap['create_time'] = ['gt', strtotime(date('Y-m-d'))];
        $rewardMap['type'] = 2;
        $userRewardList = Db::name('user_reward')->where($rewardMap)->select();
        //用户级别
        $levelist = Db::name('user_level')->select();
        $newArr = [];
        foreach ($levelist as $vl) {
            $newArr[$vl['level']] = $vl['ratio'];
        }
        foreach ($userRewardList as $val) {
            $parents = $this->threeParents($val['uid']);
            $teamParentStr = $parents['rel'];
            $parentsArr = explode(',', $teamParentStr);
            //去空去0
            array_shift($parentsArr);
            array_pop($parentsArr);
            if (!empty($parentsArr)) {
                $downlevel = null;
                foreach ($parentsArr as $parent) {
                    //级别
                    $parentLevel = Db::name('user')->where('id', $parent)->value('ulevel');
                    if($downlevel){
                        $parentReward = $val['amount'] * $newArr[$parentLevel] / 100  -  $val['amount'] * $newArr[$downlevel] / 100;
                    }else{
                        $parentReward = $val['amount'] * $newArr[$parentLevel] / 100;
                    }
                    if($parentReward > 0){
                        //收益记录
                        $this->addReward($parent, $val['uid'], 'team_integral', $parentReward, 3, '团队收益');
                        //累计收益
                        Db::name('user')->where('id', $parent)->setInc('total_share_integral', $parentReward);
                        //资金记录
                        moneyLog($parent, $val['uid'], 'team_integral', $parentReward, 5, '团队收益');
                    }

                    $downlevel = $parentLevel;
                }
            }

        }
    }

    /**
     * 两小时未付款订单取消
     */
    public function orderCan()
    {
        $baseConfig = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        $cancel_time = $baseConfig['cancel_time'];
        if(!isset($cancel_time) || $cancel_time<0){
            $cancel_time = 3600;
        }

        $map['status'] = 1;
        $map['create_time'] = ['elt', time() - $cancel_time];
        $list = Db::name('pig_order')->where($map)->select();
        if (!$list) exit;
        foreach ($list as $val) {
            model('PigOrder')->cancel($val['id']);
            //冻结帐号
            Db::name('user')->where('id',$val['uid'])->setField('status', 0);
        }
    }

    /**
     * 强制交易
     */
    public function orderCon()
    {
        $baseConfig = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        $enter_time = $baseConfig['enter_time'];
        if(!isset($enter_time) || $enter_time<0){
            $enter_time = 3600;
        }

        $map['status'] = 2;
        $map['create_time'] = ['elt', time() - $enter_time];
        $map['is_lock'] = 0;
        $list = Db::name('pig_order')->where($map)->column('id');
        if (!$list) exit;
        foreach ($list as $val) {
            model('PigOrder')->confirm($val);
        }
    }


    /**
     * 添加收益记录
     * @param $uid 用户ID
     * @param $from_id 来源ID
     * @param $currency 币种
     * @param $amount 数目
     * @param $type 类型
     * @param $note 说明
     */
    public function addReward($uid, $from_id, $currency, $amount, $type, $note)
    {
        $rewardLog = [];
        $rewardLog['uid'] = $uid;
        $rewardLog['from_id'] = $from_id;
        $rewardLog['currency'] = $currency;
        $rewardLog['amount'] = $amount;
        $rewardLog['type'] = $type;
        $rewardLog['note'] = $note;
        $rewardLog['create_time'] = time();
        Db::name('user_reward')->insert($rewardLog);
    }

    /**
     * 用户关系
     * @param $uid 用户ID
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function threeParents($uid)
    {
        $relation = Db::name('user_relation')->where('uid', $uid)->find();
        return $relation;
    }

    /**
     * 不同级别的猪对应的奖金
     * @param $id 猪ID
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function pigReward($id)
    {
        $pigInfo = Db::name('task_config')->where('id', $id)->field('id,contract_revenue,doge')->find();
        return $pigInfo;
    }


    //每天24时退还未抢到猪的用户营养分(预约不抢的不退)

    public function return_nutrient_score(){

        $yesterday = strtotime(date("Y-m-d",strtotime("-1 day")));
        $today = strtotime(date("Y-m-d"),time());
        $nowtime = date('H:i:s');
        $map = [];
        // $map['create_time'] = ['lt', $today];
        $map['create_time'] = [['gt', $yesterday],['lt', $today]];
        $map['buy_type'] = array('in','1,2');
        $map['status']=array('in','0,2');

        $pigInfo = Db::name('yuyue')->where($map)->select();
        if(count($pigInfo) >0){
            try{
                // $i=0;
                foreach($pigInfo as $key =>$val){
                    // 启动事务
                    Db::startTrans();
                    //$return_user=Db::name('user')->where('id', $val['uid'])->setInc('pay_points', $val['pay_points']);  //用户微分增加
                    $return_yuyue=Db::name('yuyue')->where('id', $val['id'])->update(['status'=>3]);    //预约退回修改状态
                    $return_moneylog=moneyLog($val['uid'], 0, 'pay_points', $val['pay_points'], 4, '抢购未中奖返还');
                    if($return_moneylog && $return_yuyue){
                        // 提交事务
                        Db::commit();
                    }else{
                        // 回滚事务
                        Db::rollback();
                    }
                }
                return 1;
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }else{
            return 1;
        }

    }





    /**推荐收益，人人享有3代
     * @param int $profit 利润比例
     */
    public function recommendReward($profit = 10)
    {
        $config = unserialize(Db::name('system')->where('name', 'base_config')->value('value'));
        //获取出售列表
        $orderMap = [];
        $orderMap['update_time'] = ['gt', strtotime(date('Y-m-d'))-86400];
        $orderMap['status'] = 3;
        $orderMap['recommend_status'] = 0;
        $pigOrderList = Db::name('pig_order')->where($orderMap)->field('id,sell_id,price')->select();

        foreach($pigOrderList as $val){
            //利润
            $contract_revenue = $val['price'] * $profit / (100+$profit);
            //得到用户推荐关系
            $parents = $this->threeParents($val['sell_id']);
            // 启动事务
            Db::startTrans();
            try {
                //一级分成
                if ($parents['pid'] > 0) {
                    //计算收益
                    $firstReward = $contract_revenue * $config['firt_parent'] / 100;
                    //添加收益记录
                    $this->addReward($parents['pid'], $val['sell_id'], 'share_integral', $firstReward, 2, '一代售出推荐');
                    //资产记录,并增加推广收益
                    moneyLog($parents['pid'], $val['sell_id'], 'share_integral', $firstReward, 4, '一代售出推荐收益');
                }
                //二级分成
                if ($parents['pid2'] > 0) {
                    $secondReward = $contract_revenue * $config['second_parent'] / 100;
                    $this->addReward($parents['pid2'], $val['sell_id'], 'share_integral', $secondReward, 2, '二代售出推荐');
                    //资产记录,并增加推广收益
                    moneyLog($parents['pid2'], $val['sell_id'], 'share_integral', $secondReward, 4, '二代售出推荐收益');
                }
                //三级分成
                if ($parents['pid3'] > 0) {
                    $thirdReward = $contract_revenue * $config['third_parent'] / 100;
                    $this->addReward($parents['pid3'], $val['sell_id'], 'share_integral', $thirdReward, 2, '三代售出推荐');
                    //资产记录,并增加推广收益
                    moneyLog($parents['pid3'], $val['sell_id'], 'share_integral', $thirdReward, 4, '三代售出推荐收益');
                }
                //修改订单状态
                Db::name('pig_order')->where('id',$val['id'])->update(['recommend_status'=>1]);
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }
    }

    //三个级别的团队收益分别如下，黄金vip享受4~10代，利润的1%，钻石vip享受4~15代，利润的3%，皇冠vip享受4~30代，利润的5%。结算在宠物出售
    public function vipTeamReward($profit=10)
    {
        $config = unserialize(Db::name('system')->where('name', 'base_config')->value('value'));
        //获得出售列表
        $orderMap = [];
        $orderMap['update_time'] = ['gt', strtotime(date('Y-m-d'))-86400];
        $orderMap['status']=3;
        $orderMap['team_status'] = 0;
        $pigOrderList = Db::name('pig_order')->where($orderMap)->field('id,sell_id,price')->select();
        //用户级别收益映射
//        $levelList = Db::name('user_level')->field('level,ratio')->select();
//        $levelMap=array_column($levelList,'ratio','level');

        foreach ($pigOrderList as $val) {
            //利润
            $contract_revenue = $val['price'] * $profit / (100+$profit);
            //得到用户推荐关系
            $parents = $this->threeParents($val['sell_id']);
            $teamParentStr = $parents['rel'];
            $parentsArr = explode(',', $teamParentStr);
            //取第4代之后元素
            $parentsArr=array_slice($parentsArr,4);
            array_pop($parentsArr);
            if(!empty($parentsArr)){
                foreach($parentsArr as $key=>$parent){
                    //获得用户级别
                    $parentLevel = Db::name('user')->where('id', $parent)->value('ulevel');
                    if($parentLevel==2&&$key<=6){
                        $parentReward = $contract_revenue * $config['gold_grade']/ 100;
                        //收益记录
                        $this->addReward($parent, $val['sell_id'], 'team_integral', $parentReward, 3, '黄金vip团队收益');
                        //资金记录
                        moneyLog($parent, $val['sell_id'], 'team_integral', $parentReward, 5, '黄金vip团队收益');
                    }
                    if($parentLevel==3&&$key<=11){
                        $parentReward = $contract_revenue *  $config['diamonds_grade']/ 100;
                        //收益记录
                        $this->addReward($parent, $val['sell_id'], 'team_integral', $parentReward, 3, '钻石vip团队收益');
                        //资金记录
                        moneyLog($parent, $val['sell_id'], 'team_integral', $parentReward, 5, '钻石vip团队收益');
                    }
                    if($parentLevel==4&&$key<=26){
                        $parentReward = $contract_revenue * $config['crown_grade'] / 100;
                        //收益记录
                        $this->addReward($parent, $val['sell_id'], 'team_integral', $parentReward, 3, '皇冠vip团队收益');
                        //资金记录
                        moneyLog($parent, $val['sell_id'], 'team_integral', $parentReward, 5, '皇冠vip团队收益');
                    }
                }
            }
            //修改订单团队收益状态
            Db::name('pig_order')->where('id',$val['id'])->update(['team_status'=>1]);
        }
    }

    //自动增加订单
    public function autoAddPigOrder()
    {
        //不推迟生成订单宠物
        $not_update = [13, 19, 26];
        //获得宠物配置
        $taskConfig = Db::name('task_config')->where('status', 1)->field('id,name,doge,contract_revenue,cycle,min_price,max_price,start_time,status')->order('min_price')->select();
        foreach ($taskConfig as $k => $task) {
            //获得出售列表
            $orderMap = [];
            $orderMap['add_status'] = 0;
            $where['delay_status'] = 0;
            $orderMap['status'] = 3;
            $orderMap['pig_id'] = $task['id'];
            $start_time = strtotime($task['start_time']) - $task['cycle'] * 86400;
            $end_time = strtotime($task['start_time']) - $task['cycle'] * 86400 + 2 * 3600+300;
            //echo $task['name'],$task['id'],':',$start_time,'-',$end_time,'<br>';
            $orderMap['update_time'] = ['between', [$start_time, $end_time]];
            $orderList = Db::name('pig_order')->where($orderMap)->field('id,uid,pig_id,price,delay_status')->select();
            //dump($orderList);
            foreach ($orderList as $val) {
                Db::startTrans();
                $task_info = $task;
                //出售价格
                $sell_price = $val['price'] * (100 + $task['contract_revenue']) / 100;
                //判断出售价格区间
                if ($sell_price > $task['max_price'] && !in_array($task['id'], $not_update)&&isset($taskConfig[$k+1])) {
                    //修改延期升级状态
                    Db::name('pig_order')->where('id', $val['id'])->setField('delay_status', 1);
                    Db::commit();
                    continue;
                }
                //小耳可升级，不延期
                if ($sell_price > $task['max_price'] && $task['id'] == 13) {
                    $task_info = $taskConfig[2];
                }
                //生成订单
                $sellOrder = [];
                $sellOrder['order_no'] = create_trade_no();
                $sellOrder['uid'] = $val['uid'];
                $sellOrder['pig_id'] = $task_info['id'];
                $sellOrder['source_price'] = $sell_price;
                $sellOrder['price'] = $sell_price;
                $sellOrder['pig_name'] = $task_info['name'];
                $sellOrder['create_time'] = time();
                $sellOrder['sell_id'] = 0;
                $sellOrder['from_id'] = $val['id'];
                $order_id = Db::name('PigOrder')->insertGetId($sellOrder);
                //用户宠物
                $saveDate = [];
                $saveDate['uid'] = $val['uid'];
                $saveDate['pig_id'] = $task_info['id'];
                $saveDate['pig_name'] = $task_info['name'];
                $saveDate['price'] = $sell_price;
                $saveDate['contract_revenue'] = $task_info['contract_revenue'];
                $saveDate['cycle'] = $task_info['cycle'];
                $saveDate['doge'] = $task_info['doge'];
                $saveDate['pig_no'] = create_trade_no();
                $saveDate['status'] = 1;
                $saveDate['create_time'] = time();
                $saveDate['end_time'] = time() + $task_info['cycle'] * 24 * 3600;
                $saveDate['order_id'] = $order_id;
                $sell_id = Db::name('user_pigs')->insertGetId($saveDate);
                //更改宠物订单状态
                $add_status=Db::name('pig_order')->where('id', $val['id'])->setField('add_status', 1);
                if($order_id&&$add_status){
                    Db::commit();
                }else{
                    Db::rollback();
                }

            }

            //获得升级宠物列表
            $where = [];
            $where['add_status'] = 0;
            $where['delay_status'] = 1;
            $where['pig_id'] = $task['id'];
            $start_time = strtotime($task['start_time']) - $task['cycle'] * 86400 - 86400;
            $end_time = strtotime($task['start_time']) - $task['cycle'] * 86400 + 2 * 3600+300 - 86400;
            $where['update_time'] = ['between', [$start_time, $end_time]];
            $updateList = Db::name('pig_order')->where($where)->field('id,uid,pig_id,price')->select();
            //dump($updateList);
            foreach ($updateList as $value) {
                Db::startTrans();
                $task_info = $taskConfig[$k + 1];
                //出售价格
                $sell_price = $value['price'] * (100 + $task['contract_revenue']) / 100;
                //生成订单
                $sellOrder = [];
                $sellOrder['order_no'] = create_trade_no();
                $sellOrder['uid'] = $value['uid'];
                $sellOrder['pig_id'] = $task_info['id'];
                $sellOrder['source_price'] = $sell_price;
                $sellOrder['price'] = $sell_price;
                $sellOrder['pig_name'] = $task_info['name'];
                $sellOrder['create_time'] = time();
                $sellOrder['sell_id'] = 0;
                $sellOrder['from_id'] = $value['id'];
                $order_id = Db::name('PigOrder')->insertGetId($sellOrder);
                //用户宠物
                $saveDate = [];
                $saveDate['uid'] = $value['uid'];
                $saveDate['pig_id'] = $task_info['id'];
                $saveDate['pig_name'] = $task_info['name'];
                $saveDate['price'] = $sell_price;
                $saveDate['contract_revenue'] = $task_info['contract_revenue'];
                $saveDate['cycle'] = $task_info['cycle'];
                $saveDate['doge'] = $task_info['doge'];
                $saveDate['pig_no'] = create_trade_no();
                $saveDate['status'] = 1;
                $saveDate['create_time'] = time();
                $saveDate['end_time'] = time() + $task_info['cycle'] * 24 * 3600;
                $saveDate['order_id'] = $order_id;
                $sell_id = Db::name('user_pigs')->insertGetId($saveDate);
                //更改宠物订单状态
                $add_status=Db::name('pig_order')->where('id', $value['id'])->setField('add_status', 1);
                if($order_id&&$add_status){
                    Db::commit();
                }else{
                    Db::rollback();
                }
            }
        }
    }


    //系统确认订单
    public function confirm_pigorder()
    {
        //获得出售列表
        // switch(date('H:i')){
        //     case '19:10':  // 小耳最长确认时间
        //         $pig_id=13;
        //     break;
        //     case '15:55':  //佩奇最长确认时间
        //         $pig_id=14;
        //     break;
        //     case '16:55': //约克最长确认时间
        //         $pig_id=15;
        //     break;
        //     case '18:55': //汉普最长确认时间
        //          $pig_id=16;
        //     break;
        //     case '19:55':  //佩吉最长确认时间
        //         $pig_id=19;
        //     break;
        //     case '20:55':  //大白最长确认时间
        //         $pig_id=20;
        //     break;
        //     }
        if(date('H:i')=='13:55'){
            $pig_id=13;
        }else if(date('H:i')=='15:55'){
            $pig_id=14;
        }else if(date('H:i')=='16:55'){
            $pig_id=15;
        }else if(date('H:i')=='18:55'){
            $pig_id=16;
        }else if(date('H:i')=='19:55'){
            $pig_id=19;
        }else if(date('H:i')=='20:55'){
            $pig_id=20;
        }else if(date('H:i')=='21:55'){
            $pig_id=26;
        }
        $orderMap = [];
        $orderMap['add_status']=0;
        $orderMap['status']=2;
        $orderMap['pig_id']=$pig_id;


        $pigInfo = Db::name('task_config')->where('id',$pig_id)->find();
        $baseConfig = unserialize(Db::name('system')->where('name','base_config')->value('value'));
        $orderList = Db::name('pig_order')->where($orderMap)->field('id,uid,pig_id,sell_id,price')->select();
        //未付款自动取消订单
        $this->autoOrderCancel($baseConfig);
        if(count($orderList) >0){
            foreach($orderList as $key =>$r){
                Db::startTrans();
                $sell_user=Db::name('user')->where('id',$r['sell_id'])->find();
                // $sell_user['violation_sum']= $sell_user['violation_sum'] +1;
                // if($sell_user['violation_sum'] >=3){
                //     $red=Db::name('user')->where('id',$r['sell_id'])->update(array('status'=>0));  //违规扣分次数大于等于3次自动封号
                // }else{
                //     $red=1;
                // }
                $re = Db::name('pig_order')->where('id',$r['id'])->setField(['status'=>3,'update_time'=>time()]);  //系统确认
                // $resd=Db::name('user')->where('id',$r['sell_id'])->setInc('violation_sum',1);                    //违规扣分次数加1
                $resd = Db::name('user')->where('id',$r['sell_id'])->update(array('status'=>0));  //违规冻结
                $rss= moneyLog($r['sell_id'],0,'pay_points',0,9,'超时不确认收款冻结');                       //记录
                if($re  && $rss && $resd){
                    //把猪添加到买方
                    $userPig = [];
                    $sell_end_time = $baseConfig['sell_end_time'];
                    if(!isset($sell_end_time) || $sell_end_time<0){
                        $sell_end_time = 0;
                    }

                    $userPig['uid'] = $r['uid'];
                    $userPig['status'] = 0;
                    $userPig['from_id'] = $r['sell_id'];
                    $userPig['price'] = $r['price'];
                    $userPig['create_time'] = time();
                    $userPig['end_time'] = time()+$pigInfo['cycle']*24*3600-$sell_end_time;
                    Db::name('user_pigs')->where('order_id',$r['id'])->update($userPig);
                    //奖励PIG
                    moneyLog($r['uid'],0,'pig',$pigInfo['pig'],9,'买入奖励wia');

                    //奖金记录
                    addReward($r['uid'],0,'pig',$pigInfo['pig'],5,'交易奖励wia');
                    // 提交事务
                    Db::commit();

                } else{
                    // 回滚事务
                    Db::rollback();
                }
            }
            return 1;
        }else{
            return 2;
        }


    }

    /**
     * 两小时未付款自动取消订单
     */
    public function autoOrderCancel($baseConfig)
    {
        $cancel_time = $baseConfig['cancel_time'];
        if(!isset($cancel_time) || $cancel_time<0){
            $cancel_time = 3600;
        }
        $map['status'] = 1;
        $map['create_time'] = ['elt', time() - $cancel_time];
        $list = Db::name('pig_order')->where($map)->select();
        if (!$list) return;
        foreach ($list as $val) {
            model('PigOrder')->cancel($val['id']);
            //冻结帐号
            //Db::name('user')->where('id',$val['uid'])->setField('status', 0);
        }
    }


    //释放感恩收益
    public function shifang_profit(){
        $map['gy_integral']= ['gt', 0];
        $map['is_shifang']=0;
        $userlist=Db::name('user')->where($map)->field('id,gy_integral,doge,username')->select();
        if(count($userlist)){
            $base_config=unserialize(Db::name('system')->where('name', 'base_config')->value('value'));
            foreach($userlist as $key =>$r){
                Db::startTrans();
                $shifang_profit=$r['gy_integral']*$base_config['release_ratio']/100;
                $re=Db::name('user')->where(array('id'=>$r['id']))->setInc('doge',$shifang_profit);
                $ree=Db::name('user')->where(array('id'=>$r['id']))->setDec('gy_integral',$shifang_profit);
                $rss=Db::name('user')->where(array('id'=>$r['id']))->update(['is_shifang'=>1]);
                $arr=array('uid'=>$r['id'],'shifang_profit'=>$shifang_profit,'original_profit'=>$r['gy_integral'],'original_doge'=>$r['doge'],'now_doge'=>$r['doge'] +$shifang_profit,'note'=>'系统释放','create_time'=>date('Y-m-d H:i:s'));
                $rdd=Db::name('shifang_profit')->insert($arr);
                if($re && $ree && $rss && $rdd){
                    Db::commit();
                }else{
                    Db::rollback();
                }
            }
        }
    }

    //释放状态清0
    public function shifang_status(){
        $userlist=Db::name('user')->where('1=1')->update(array('is_shifang'=>0));
        if($userlist){
            return 1;
        }
    }

    public function dd(){
        echo 222;
        die;
    }

    //扣除双倍退回营养分
    public function cancel_score()
    {
        $map=[];
        $map['status']=3;
        $pigInfo = Db::name('yuyue')->where($map)->select();
        if(!$pigInfo){
            return;
        }
        foreach($pigInfo as $val){
            moneyLog($val['uid'], 0, 'pay_points', -$val['pay_points'], 4, '扣除抢购未中奖双倍返还营养分');
        }
    }
}

