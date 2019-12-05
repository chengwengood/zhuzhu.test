<?php
/*
'''''''''''''''''''''''''''''''''''''''''''''''''''''''''
author:ming    contactQQ:811627583
''''''''''''''''''''''''''''''''''''''''''''''''''''''''''
 */
namespace app\admin\controller;

use app\common\controller\AdminBase;
use think\Config;
use think\Db;
use think\Session;
/**
 * 管理员管理
 * Class AdminUser
 * @package app\admin\controller
 */
class Question extends AdminBase
{
    protected function _initialize()
    {
        parent::_initialize();
    }



    /**
     * 商品列表
     * @return mixed
     */
    public function index()
    {   $map =  [];
        $keyword=$this->request->param('keyword');

        if($keyword){
            $map['mobile|content']=['like',"%{$keyword}%"];
        }

        $list=Db::name('question')->where($map)->paginate(12,false,['query'=>$this->request->param()]);
        $this->assign('list',$list);
        $this->assign('page',$list->render());

        return $this->fetch();
    }

    /**
     * 编辑
     * @return mixed
     */
    public function edit()
    {
        $id=$this->request->param('id')?$this->request->param('id'):0;
        if($this->request->isPost()){
            $data=$this->request->post();
            //dump($data);die;
            if($id){
                $re=model('Question')->allowField(true)->save($data,['id'=>$id]);
            }else{
                $re=model('Question')->allowField(true)->save($data);
            }
            if($re!==false){
                $this->success('操作成功',url('index'));
                exit;
            }else{
                $this->error('操作失败');
            }
        }
        $info=Db::name('question')->where('id',$id)->find();
        $this->assign('info',$info);
        return $this->fetch();
    }
    /**
     * 删除商品
     * @param int   $id
     * @param array $ids
     */
    public function del($id = 0, $ids = [])
    {
        $id = $ids ? $ids : $id;
        if ($id) {
            if (model('Question')->destroy($id)) {
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('请选择需要删除的工单');
        }
    }


}