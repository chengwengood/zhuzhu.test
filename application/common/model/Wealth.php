<?php
/*
'''''''''''''''''''''''''''''''''''''''''''''''''''''''''
author:ming    contactQQ:811627583
''''''''''''''''''''''''''''''''''''''''''''''''''''''''''
 */
namespace app\common\model;

use think\Model;
use think\Db;
class Wealth extends Model
{
    protected $table = 'wym_money_log';
    public function getCurrencyAttr($value)
    {
        $currency = [
            //'money'=>'现金币',
            'pay_points' => '微分',
            'share_integral' => '推广收益',
            'team_integral' => '团队收益',
            'gy_integral' =>'感恩收益',
            'zc_integral' => '收益转存',
            'contract_revenue' => '智能合约',
            'wia' => 'OCE币',
            'pig' => 'OCE币',
            'doge'=>'GGE币'
        ];
        return $currency[$value];
    }


}