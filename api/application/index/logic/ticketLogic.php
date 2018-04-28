<?php
namespace app\index\logic;

use think\Model;

use app\index\model\Ticket as DBticket;
use app\index\logic\LogicTools as Tools;
use app\index\model\Route as DBroute;

/**
 * @author sunmingcong@tiaozhan.com
 * 
 * @copyright (c) sunmingcong 2018 . All Rights Reserved.
 */
class ticketLogic extends Model
{

  /**
   * 全局错误码
   */

  /**
   * 无余票
   */
  const NO_REMAIN = 10005;
  /**
   * 操作成功
   */
  const REQUEST_OK = 200;
  /**
   * 车票信息有误（wrong ticket_id）
   */
  const WRONG_TICKET = 10006;
  /**
   * 数据库数据重复
   */
  const EXISTED_DB = 10007;
  /**
   * 数据库操作出错或数据库连接出错
   */
  const DB_ERROR = 10003;


  /**
   * 车次票务状态码
   */
  const OUTOFLINE    = 100;
  const BEFORETRAVEL = 101;
  const NOREMAIN     = 102;
  const AFTERTRAVEL  = 103;
  const CANCELED     = 104;

  /**
   * 获取车票相关信息
   * 
   * @param  $ticket_id 车票id
   * 
   * @return $remain 剩余票数
   * @return $from   线路起点
   * @return $to     线路终点
   */
  public static function getTicketInfo ($ticket_id) {

  }
  /**
   * 车次  管理员方法
   * 
   * 1、设置车次开行时间
   * 2、统计购票人数和购票人员信息
   * 3、设置车票上架时间
   * 
   * @param $admin       管理员
   * @param $method      操作 (包括新增车次、发布车次、管理车次、删除车次)
   * @param $route_id    线路id
   * @param $travel_time 车次开行时间
   * @param $total       总票数
   */
  public static function ticketAdmin ($method,$ticket_id = null,$admin,$route_id,$travel_time,$total,$pub_time) {
    //验证管理员权限
    // $admin_auth = User::where("userid",$admin)->find()->auth;
    switch ($method) {
      case "NEW" :
      // 新增车次信息 (默认发布时间为提交请求后半小时)
      $ticket = new DBticket();
      $ticket->ticket_status = self::OUTOFLINE;
      $ticket->ticket_id     = Tools::createRandomString(8);
      $ticket->route_id      = $route_id;
      $ticket->total         = $total;
      $ticket->remain        = $total;
      $ticket->travel_time   = $travel_time;
      $ticket->pub_time      = $pub_time;
      $ticket->active_time   = time();

      $DBstatus = $ticket->save();

      return $DBstatus ? self::REQUEST_OK : self::DB_ERROR;
      break;
      case "DEL" :
      // 删除或下架某车次 (初步设定管理员即可操作)
      $ticket = DBticket::where("ticket_id",$ticket_id)->find();

      $DBstatus = $ticket->delete();

      return $DBstatus ? self::REQUEST_OK : self::DB_ERROR;
      break;
    }
  }
  /**
   * 线路设置管理员方法
   * 
   * 1、设置线路起始地点和终到地点
   * 
   * @param $route_from 起始地点
   * @param $route_to   终到地点
   * @param $admin      管理员
   */
  public static function routeAdmin ($route_from,$route_to,$admin) {
    //验证管理员权限
    // $admin_auth = User::where("userid",$admin)->find()->auth;
    $map['route_from'] = $route_from;
    $map['route_to']   = $route_to;
    // 判断是否有重复线路
    if (is_null(DBroute::where($map)->find())) {
      $route = new DBroute ();
      $route->route_from = $route_from;
      $route->route_to   = $route_to;
      $route->save();
      return Tools::ResponseMessages(self::REQUEST_OK,null,"ok",null);
    } else {
      return Tools::ResponseMessages(self::EXISTED_DB,"路线信息重复",null,null);
    }
  }

  /**
   * 扣减剩余票数方法
   * 
   * @param $ticket_id 车票id
   */
  protected static function reduceRemain ($ticket_id) {
    $ticket = DBticket::where("ticket_id",$ticket_id)->find();
    if ($ticket) {
      if ($ticket->remain <= 0) {
        return Tools::ResponseMessages(self::NO_REMAIN,"余票数量等于零或扣减后数量小于零",null,null);
      } else {
        $ticket->remain --;
        if ($ticket->remain === 0) {
          $ticket->ticket_status = self::NOREMAIN;
        }
        $ticket->save();
        return Tools::ResponseMessages(self::REQUEST_OK,null,"操作成功：扣减余票",null);
      }
    } else {
      return Tools::ResponseMessages(self::WRONG_TICKET,"车票信息有误或车票id不存在",null,null);
    }
  }
  /**
   * 增加余票数量方法
   * 
   * 适用：
   * 1、用户退票
   * 2、
   * 
   * @param $ticket_id 车票id
   */
  protected static function addRemain () {
    $ticket = DBticket::where("ticket_id",$ticket_id)->find();
    if ($ticket) {
      if ($ticket->remain >= $ticket->total) {
        return Tools::ResponseMessages(self::NO_REMAIN,"余票数量等于零或增加后数量大于车票总量",null,null);
      } else {
        $ticket->remain ++;
        $ticket->save();
        return Tools::ResponseMessages(self::REQUEST_OK,null,"操作成功：增加余票",null);
      }
    } else {
      return Tools::ResponseMessages(self::WRONG_TICKET,"车票信息有误或车票id不存在",null,null);
    }
  }
}
?>