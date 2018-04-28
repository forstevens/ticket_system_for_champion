<?php
namespace app\index\logic;

use think\Model;

use app\index\model\User;
use app\index\logic\LogicTools as Tools;



/**
 * 全局错误码
 */

/**
 * 权限不足或用户信息不存在
 */
const NO_AUTHORITY = 2403;
/**
 * 请求成功
 */
const REQUEST_OK = 200;

/**
 * @author sunmingcong@tiaozhan.com
 * 
 * @copyright (c) sunmingcong 2018 . All Rights Reserved.
 */
class Verifier extends Model
{
  public static function index () {
    return "this is verifier";
  }
  /**
   * 管理员权限验证器
   * 
   * @param $admin 管理员userid
   */
  public static function adminVerifier ($admin) {
    $user = User::where("userid",$admin)->find();
    if ($user) {
      if ($user->auth != 0) {
        return Tools::ResponseMessages(self::REQUEST_OK,null,"ok",["admin_auth" => $user->auth]);
      } else {
        return Tools::ResponseMessages(self::NO_AUTHORITY,"NO Authority",null,null);
      }
    } else {
      return Tools::ResponseMessages(self::NO_AUTHORITY,"NO Authority",null,null);
    }
  }
}
?>