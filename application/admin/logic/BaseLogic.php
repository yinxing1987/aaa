<?php
namespace app\admin\logic;
use Think\Request;
use think\Db;
use think\Model;

class BaseLogic extends Model
{
    private $model='';
    private $id_alias='';//别名id：表名+下划线+id，如"user_id"
    private $slave_model = '';//从表模型
    private $slave_relation_field='';
    private $flag_model = '';
    private $flag_opt = '';
    
    function __construct($model='',$mp=null)
    {
        if($model)
            $this->model = "admin/".$model;
        $this->id_alias = $mp["id_alias"];
        if($mp["slave_model"])
            $this->slave_model = "admin/".$mp["slave_model"];
        $this->slave_relation_field = $mp["slave_relation_field"];
        if($mp["flag_model"])
            $this->flag_model = "admin/".$mp["flag_model"];
        $this->flag_opt = $mp["flag_opt"];
    }

    //redis锁
    public function lockRedis($key, $expire=5)
    {
        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        if($conn)
        {
            $is_lock = $redis->setnx($key, time()+$expire);
            if(!$is_lock){
                $lock_time = $redis->get($key);
                if(time()>$lock_time){
                    $this->unlockRedis($key);
                    $is_lock = $redis->setnx($key, time()+$expire);
                }
            }
            $redis->close();
        }
        return $is_lock? true : false;
    }

    //Redis解锁
    public function unlockRedis($key)
    {
        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        if($conn)
        {
            $res = $redis->del($key);
            $redis->close();
        }
        return $res ;
    }


    public function queueListRedis($queue_name,$start,$end)
    {
        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        $res = null ;
        if($conn)
        {
            $res = $redis->lrange($queue_name,$start,$end);//将
            $redis->close();
        }
        return $res;
    }

    public function queuePushRedis($queue_name,$info)
    {
        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        $res = null ;
        if($conn)
        {
            if($info)
            {
                $res = $redis->rpush($queue_name,json_encode($info));//将
                //echo $res;
            }
            
            $redis->close();
        }
        return $res;
    }
    public function queuePopRedis($queue_name)
    {
        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        $json = "" ;
        $info = null ;
        if($conn)
        {
            $json = $redis->lpop($queue_name);//将
            $redis->close();
        }
        if($json)
        {
            $info = object_to_array(json_decode($json));
        }
        return $info;
    }
    
    public function setRedis($key,$value,$timeout=0){

        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        $res = null ;
        if($conn)
        {
            if($timeout>0)
            {
                $res = $redis->set($key, $value,$timeout);
            }
            else
            {
                $res = $redis->set($key, $value);   
            }
            $redis->close();
        }
        return $res;
    }

    public function getRedis($key){

        $redis_config = config("redis");
        $redis = new \Redis();
        $conn = $redis->connect($redis_config["ip"], $redis_config["port"]);
        if($redis_config["pwd"])
        {
            $conn = $redis->auth($redis_config["pwd"]);
        }
        $res = null ;
        if($conn)
        {
            $res = $redis->get($key);
            $redis->close();
        }
        return $res;

    }
    

    /**   
    * 根据条件来检索数据，并排序分页 
    * 
    * @access public 
    * @param $where array 搜索条件
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @param $page int 页码
    * @param $page_size int 每页显示记录数
    * @param $count int 总记录数
    * @return array 符合条件的数据集
    */  
    public function search($search=null,$order_by='',$page=1,$page_size=100,&$count=0)
    {
        $res = model($this->model)->search($search,$order_by,$page,$page_size,$count);
        $res = $this->bind($res);
        return $res;
    }
    
    /**   
    * 根据id来获取单条视图信息
    * 
    * @access public 
    * @param $id int 
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的单条记录
    */ 
    public function viewInfo($id,$field='',$order='')
    {
        $res = model($this->model)->viewInfo($id,$field,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据条件来获取单条视图信息
    * 
    * @access public 
    * @param $where array 搜索条件
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的单条记录
    */ 
    public function viewInfoBy($where,$field='',$order='')
    {
        $res = model($this->model)->viewInfoBy($where,$field,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据ids(一个或多个id)来获取视图数据集
    * 
    * @access public 
    * @param $ids int/string/array 单个(如1，或'1'，或[1])或多个id值(如'1,2'，或[1,2])
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $limit int 要获取的数据数量
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的数据集
    */
    public function viewListByIds($ids,$field='',$limit=100,$order='id desc')
    {
        $res = model($this->model)->viewListByIds($ids,$field,$limit,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据条件来获取视图数据集
    * 
    * @access public 
    * @param $where array 搜索条件
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $limit int 要获取的数据数量
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的数据集
    */
    public function viewListBy($where=null,$field='',$limit=100,$order='id desc')
    {
        $res = model($this->model)->viewListBy($where,$field,$limit,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据id来获取单条记录
    * 
    * @access public 
    * @param $id int 
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @param $is_lock bool 是否需要锁定该条记录（用于事务）
    * @return array 符合条件的单条记录
    */ 
    public function info($id,$field='',$order='',$is_lock=false)
    {
        $res =  model($this->model)->info($id,$field,$order,$is_lock);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据条件来获取单条记录 
    * 
    * @access public 
    * @param $where array 搜索条件
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的单条记录
    */ 
    public function infoBy($where,$field='',$order='')
    {
        $res =  model($this->model)->infoBy($where,$field,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据ids(一个或多个id)来获取数据集
    * 
    * @access public 
    * @param $ids int/string/array 单个(如1，或'1'，或[1])或多个id值(如'1,2'，或[1,2])
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $limit int 要获取的数据数量
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的数据集
    */
    public function listByIds($ids,$field='',$limit=100,$order='id desc')
    {
        $res =  model($this->model)->listByIds($ids,$field,$limit,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据条件来获取数据集
    * 
    * @access public 
    * @param $where array 搜索条件
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $limit int 要获取的数据数量
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的数据集
    */
    public function listBy($where=null,$field='',$limit='',$order='id desc')
    {
        $res =  model($this->model)->listBy($where,$field,$limit,$order);
        $res = $this->bind($res);
        return $res;
    }

    /**   
    * 根据条件来获取数据集
    * 
    * @access public 
    * @param $where array 搜索条件
    * @param $field string 要获取的字段，多个用英文逗号隔开，如'id,name'
    * @param $limit int 要获取的数据数量
    * @param $page int 页码 
    * @param $order string 排序字段，多个用英文逗号隔开，如'id desc,name asc'
    * @return array 符合条件的数据集
    */
    public function listPageBy($where=null,$field='',$page=1,$limit=1000,$order='id desc')
    {
        $res =  model($this->model)->listPageBy($where,$field,$page,$limit,$order);
        return $res;
    }


    /**   
    * 添加单条记录
    * 
    * @access public 
    * @param $data array 数据
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return array 提交的数据+id
    */
    public function add($data=null,$is_edit_slave_model=true)
    {
        $res =  $this->insert($data,$is_edit_slave_model);
        return $res;
    }

    /**   
    * 批量添加
    * 
    * @access public 
    * @param $data array 数据集
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return array 数据集
    */
    public function adds($list=null,$is_edit_slave_model)
    {
        $res =  $this->inserts($list,$is_edit_slave_model);
        return $res;
    }


    /**   
    * 更新单条记录
    * 
    * @access public 
    * @param $data array 数据集
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return int 返回影响行数，失败返回false
    */
    public function upd($data=null,$is_edit_slave_model=true)
    {
        $res =  $this->change($data,$is_edit_slave_model);
        return $res;
    }

    /**   
    * 批量更新
    * 
    * @access public 
    * @param $list array 数据集
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return int 返回影响行数，失败返回false
    */
    public function upds($list=null,$is_edit_slave_model=true)
    {
        $res =  $this->changes($list,$is_edit_slave_model);
        return $res;
    }

    /**   
    * 批量更新某一个属性的值
    * 
    * @access public 
    * @param $ids int/string/array 单个(如1，或'1'，或[1])或多个id值(如'1,2'，或[1,2])
    * @param $field_name string 字段名称(如：'status')
    * @param $field_val 字段值(如：'1')
    * @return int 返回影响行数，失败返回false
    */
    public function updAttr($ids,$field_name,$field_val)
    {
        $res =  model($this->model)->updAttr($ids,$field_name,$field_val);
        return $res;
    }

    /**   
    * 根据条件来批量更新记录
    * 
    * @access public 
    * @param $data array 数据集，如['name'=>'zms','age'=>18]
    * @param $where 更新条件，如['id'=>1]
    * @return int 返回影响行数，失败返回false
    */
    public function updBy($data, $where)
    {
        $res =  model($this->model)->updBy($data, $where);
        return $res;
    }

    /**   
    * 批量更新排序
    * 
    * @access public 
    * @param $arr_sorts array 如['1'=>50,'2'=>40]
    * @return int 返回影响行数，失败返回false
    */
    public function updSort($arr_sorts)
    {
        foreach ($arr_sorts as $key => $value)
        {
            $this->updAttr($key,"sort",$value);
        }
        return true;
    }

    /**   
    * 根据单个id或多个id来删除
    * 
    * @access public 
    * @param $ids int/string/array 单个(如1，或'1'，或[1])或多个id值(如'1,2'，或[1,2])
    * @return int 返回影响行数
    */
    public function del($ids)
    {
        $res =  model($this->model)->del($ids);
        return $res;
    }

    /**   
    * 根据条件来删除
    * 
    * @access public 
    * @param $where array 条件
    * @return int 返回影响行数
    */
    public function delBy($where)
    {
        $res =  model($this->model)->delBy($where);
        return $res;
    }

    /**   
    * 根据条件统计数量
    * 
    * @access public 
    * @param $where array 条件
    * @param $field string 字段名，如"name"，或者"distinct name"
    * @return int 数量
    */
    public function countBy($where,$field="id")
    {
        $res =  model($this->model)->countBy($where,$field);
        return $res;
    }

    /**   
    * 根据条件统计最大值
    * 
    * @access public 
    * @param $where array 条件
    * @param $field string 字段名，如"score"，必须为数值类型
    * @return 数值类型 获取最大值
    */
    public function maxBy($where,$field)
    {
        $res =  model($this->model)->maxBy($where,$field);
        return $res;
    }

    /**   
    * 根据条件统计最小值
    * 
    * @access public 
    * @param $where array 条件
    * @param $field string 字段名，如"score"，必须为数值类型
    * @return 数值类型 获取最小值
    */
    public function minBy($where,$field)
    {
        $res =  model($this->model)->minBy($where,$field);
        return $res;
    }

    /**   
    * 根据条件获取平均值
    * 
    * @access public 
    * @param $where array 条件
    * @param $field string 字段名，如"score"，必须为数值类型
    * @return 数值类型 获取平均值
    */
    public function avgBy($where,$field)
    {
        $res =  model($this->model)->avgBy($where,$field);
        return $res;
    }

    /**   
    * 根据条件求和
    * 
    * @access public 
    * @param $where array 条件
    * @param $field string 字段名，如"score"，必须为数值类型
    * @return 数值类型 求和
    */
    public function sumBy($where,$field)
    {
        $res =  model($this->model)->sumBy($where,$field);
        return $res;
    }

    public function inc($where,$field_name,$field_val=1)
    {
        $res =  model($this->model)->inc($where,$field_name,$field_val);
        return $res;
    }

    public function dec($where,$field_name,$field_val)
    {
        $res =  model($this->model)->dec($where,$field_name,$field_val);
        return $res;
    }

    /**   
    * 获取所有表名
    * 
    * @access public 
    * @return array 返回所有表名
    */
    public function tableList()
    {
        $res =  model($this->model)->tableList();
        return $res;
    }


    ###########################################################################################################

    /**   
    * 设置标记 
    * 
    * @access public 
    * @param $ids array/string/int 一个或多个id，如[1,2,3],"1,2,3",1
    * @param $flag_id int 标记id
    * @return bool 成功返回true，失败返回false
    */ 
    public function setFlag($ids,$flag_id)
    {
        $list = $this->listByids($ids);
        $ids = array_column($list,"id");
        if($ids)
        {
            foreach($ids as $id)
            {
                $data[$this->id_alias] = $id;
                $data["flag_id"] = $flag_id;
                $res = logic($this->flag_model)->infoBy($data); 
                if(!$res)
                {
                    logic($this->flag_model)->add($data);    
                }
            }
            return true;
        }
        else
        {
            $this->error = "参数错误" ;
            return false;
        }
    }

    /**   
    * 取消标记 
    * 
    * @access public 
    * @param $ids array/string/int 一个或多个id，如[1,2,3],"1,2,3",1
    * @param $flag_id int 标记id
    * @return bool 成功返回true，失败返回false
    */ 
    public function unsetFlag($ids,$flag_id)
    {
        if($ids)
        {
            foreach ($ids as $id) 
            {
                $data[$this->id_alias] = $id;
                $data["flag_id"] = $flag_id;
                logic($this->flag_model)->delBy($data);
            }
        }
        return true;
    }

    ###########################################################################################################

    private function bind($data)
    {
        if(!$data)
            return null;

        if( $this->flag_opt && $this->flag_model)
        {
            $data = $this->bindFlag($data);
        }
        return $data;
    }


    private function bindFlag($data)
    {
        //获取id
        if(is_array($data))
        {
            $ids = array_column($data,"id");
        }
        else
        {
            $ids = $data["id"];
        }

        //或去标记列表
        $where[$this->id_alias] = array("in",$ids);
        $list = logic($this->flag_model)->listBy($where);
        if($list)
        {
            foreach($data as &$row) 
            {
                foreach ($list as $r) 
                {
                    if($row["id"] == $r[$this->id_alias])
                    {
                        $row["flag_ids"][] = $r["flag_id"];
                        $row["flag_names"][] = $this->flag_opt[$r["flag_id"]] ;
                    }
                }
            }
        }
        return $data;
    }

    private function bindType($data)
    {
        if(is_array($data))
        {
            $type_ids = array_column($data,"type_id");
        }
        else
        {
            $type_ids = $data["type_id"];
        }
        $list = logic($this->type_model)->listByIds($type_ids);
        if($list)
        {
            foreach($data as &$row) 
            {
                foreach ($list as $r) 
                {
                    if($row["type_id"] == $r["id"])
                    {
                        $row["type_name"] = $r["name"] ;
                    }
                }
            }
        }
        
        return $data;
    }

    /**   
    * 添加单条记录
    * 
    * @access private 
    * @param $data array 数据
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return array 提交的数据+id
    */
    private function insert($data,$is_edit_slave_model=true)
    {
        if($this->slave_model && $is_edit_slave_model)
        {
            Db::startTrans();
            try
            {
                $data = model($this->model)->add($data) ;//添加主表信息
                if($data["id"])
                {
                    //$data["slave_list"] = [['body'=>'aaa'],['body'=>'bbb'],['body'=>'ccc']];
                    if($data["slave_list"])//主表和从表是一对多的关系
                    {
                        $is_break = false;
                        $i = 0;
                        foreach($data["slave_list"] as $row)
                        {
                            $row[$this->slave_foreign_key] = $data["id"];
                            $result = logic($this->slave_model)->add($row) ;
                            if(!$result)
                            {
                                $is_break = true;
                                break;
                            }
                            if($i==0)
                            {
                                $res = $result;
                            }
                            $i++;
                        }
                        if($is_break)
                        {
                            $res = false;
                        }
                    }
                    else//主表和从表是一对一的关系
                    {
                        $res = logic($this->slave_model)->add($data) ;
                    }
                    if($res)
                    {
                        Db::commit();
                        return $res;
                    }
                    else
                    {
                        Db::rollback();
                        $this->error = logic($this->slave_model)->getError() ;
                        return false;
                    }
                }
                else
                {
                    Db::rollback();
                    $this->error = model($this->model)->getError() ;
                    return false;
                }
            }
            catch (\Exception $e) 
            {
                Db::rollback();
                $this->error = "异常:".$e->getMessage() ;
                return false;
            }
        }
        else
        {
            $res = model($this->model)->add($data) ;
            if(!$res)
            {
                $this->error = model($this->model)->getError() ;
                echo model($this->model)->getError();
            }
            return $res;
        }
    }

    /**   
    * 批量添加
    * 
    * @access private 
    * @param $data array 数据集
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return array 数据集
    */
    private function inserts($list,$is_edit_slave_model=true)
    {

        if($this->slave_model && $is_edit_slave_model)
        {
            Db::startTrans();
            try
            {
                $is_break = false ;
                $i=0;
                foreach($list as $row) 
                {
                    $data = $this->add($row,$is_edit_slave_model) ;
                    if($i==0)
                    {
                        $res = $data ;
                    }
                    $i++ ;
                    if(!$data)
                    {
                        $is_break = true;
                        break;
                    }
                }
                if($is_break)
                {
                    Db::rollback();
                    if($this->error)
                    {
                        $msg = "第".$i."条记录添加失败:".$this->error;
                        $this->error = $msg ;
                    }
                    return false;
                }
                else
                {
                    Db::commit();
                    return $res;
                }
            } 
            catch (\Exception $e)
            {
                Db::rollback();
                $this->error = "出现异常" ;
                return false;
            }
        }
        else
        {
            $res = model($this->model)->adds($list) ;
            if(!$res)
            {
                $this->error = model($this->model)->getError() ;
            }
            return $res;
        }
    }

    /**   
    * 更新单条记录
    * 
    * @access private 
    * @param $data array 数据集
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return int 返回影响行数，失败返回false
    */
    private function change($data,$is_edit_slave_model=true)
    {
        if($this->slave_model && $is_edit_slave_model)
        {
            Db::startTrans();
            try
            {
                $res = model($this->model)->upd($data) ;//更新主表信息
                if($res !== false)
                {
                    //$data["slave_list"] = [['id'=>'1','body'=>'测试2'],['id'=>'2','body'=>'测试2']];
                    if($data["slave_list"])//主表和从表是一对多的关系
                    {
                        $is_break = false;
                        foreach($data["slave_list"] as $row)
                        {
                            $result = logic($this->slave_model)->upd($row) ;
                            if($result === false)
                            {
                                $is_break = true;
                                break;
                            }
                        }
                        if($is_break)
                        {
                            $res = false;
                        }
                    }
                    else//主表和从表是一对一的关系
                    {
                        $res = logic($this->slave_model)->upd($data) ;
                    }
                    if($res !== false)
                    {
                        Db::commit();
                        return $res;
                    }
                    else
                    {
                        Db::rollback();
                        $this->error = logic($this->slave_model)->getError();
                        return $false;
                    }
                }
                else
                {
                    Db::rollback();
                    $this->error = model($this->model)->getError();
                    return false;
                }
            }
            catch (\Exception $e) 
            {
                Db::rollback();
                $this->error = "出现异常" ;
                return false;
            }
        }
        else
        {
            $res = model($this->model)->upd($data) ;//更新主表信息
            if($res === false)
            {
                $this->error = model($this->model)->getError();
            }
            return $res;
        }
    }

    /**   
    * 批量更新
    * 
    * @access private 
    * @param $list array 数据集
    * @param $is_edit_slave_model bool 是否操作从表模型
    * @return int 返回影响行数，失败返回false
    */
    private function changes($list,$is_edit_slave_model=true)
    {
        if(isset($this->slave_model) && $is_edit_slave_model)
        {
            Db::startTrans();
            try
            {
                $is_break = false ;
                $i = 0;
                foreach ($list as $row) 
                {
                    $i++ ;
                    $res = $this->upd($row,$is_edit_slave_model) ;
                    if($res === false)
                    {
                        $is_break = true;
                        break;
                    }
                }
                if($is_break)
                {
                    Db::rollback();
                    if($this->error)
                    {
                        $msg = "第".$i."条记录修改失败:".$this->error ;    
                        $this->error = $msg;
                    }
                    
                    return false;
                }
                else
                {
                    Db::commit();
                    return $res;
                }
            } 
            catch (\Exception $e)
            {
                Db::rollback();
                $this->error = "出现异常" ;
                return false;
            }
        }
        else
        {
            $res = model($this->model)->upds($list) ;//更新主表信息
            if($res === false)
            {
                $this->error = model($this->model)->getError();
            }
            return $res;
        }
    }

    ###########################################################################################################
    #
    public function login($data,$app_info){
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $app_info['wx_app_id'] . '&secret=' . $app_info['wx_app_secret']  . '&js_code=' . $data['code'] . '&grant_type=authorization_code';
        $str = curl_get($url);
        $info = json_decode($str, true);
        return $info;
    }
}