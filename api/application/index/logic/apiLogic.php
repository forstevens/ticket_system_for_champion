<?php

namespace app\index\Logic;

include_once(__DIR__."/error.inc.php");

use think\Model;
use app\index\model\Auth;
use app\index\model\User;
use app\index\Logic\LogicTools as Tools;
use app\index\Logic\Token as myTokenLogic;

/**
 * @package app\index\Controller
 * @author sunmingcong@tiaozhan.com
 * 
 */
class ApiLogic extends model{

  /**
   * 企业微信api
   * 
   * 企业身份验证 保留信息
   */

  /**
   * 企业微信 应用专属密钥
   */
  const SECRET = "0140fc3ce92bda900268a6db7d06d3c6";
  /**
   * 企业微信 应用ID
   */
  const AGENT_ID = "1000002";
  /**
   * 企业微信 企业CorpID
   */
  const CORP_ID = "wx5a5d51a906e986be";

  /**
   * 全局状态码（错误码）
   */

  /**
   * access_token过期
   */
  const Expired_ACCESS_TOKEN = "42001";
  /**
   * CODE参数非法或不可用
   */
  const INVALID_CODE = "40029";
  /**
   * 全局错误
   */
  const QJ_ERROR = "304";
  /**
   * ticket过期
   */
  const Expired_TICKET = "84014";
  /**
   * ticket不存在或非法不可用
   */
  const INVALID_TICKET = "84015";
  /**
   * 请求OK
   */
  const STATUS_OK = "200";
  /**
   * 非企业内部职员
   */
  const NON_STRFF = "204";

  /**
   * 企业微信api 保留请求地址
   */

  /**
   * access_token获取 根地址
   */
  const ACCESS_TOKEN_URL = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?";
  /**
   * 权限验证失败 重定向首选URI
   */
  const REDIRECT_URL = "https://guo.tiaozhan.com/html/passport.html";
  /**
   * user_code获取 根地址
   */
  const USER_CODE_URL = "https://open.weixin.qq.com/connect/oauth2/authorize?";
  /**
   * user_ticket获取 根地址
   */
  const USER_TICKET_URL = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?";
  /**
   * 用户信息获取 根地址
   */
  const USER_INFO_URL = "https://qyapi.weixin.qq.com/cgi-bin/user/get?";
  /**
   * jsapi_ticket获取 根地址
   */
  const JSAPI_TICKET_URL = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?";
  /**
   * 平台前端页面 根地址
   */
  const BASE_URL = "https://guo.tiaozhan.com/html";

  protected $cURL_req = null;

  public function __construct(){
    $this->cURL_req = curl_init();
  }
  public function __destruct(){
    if(is_resource($this->cURL_req)){
      curl_close($this->cURL_req);
    }
  }

  /**
   * 数据库中access_token未过期失效时
   * 从数据库中取出access_token
   * 
   * 数据库中access_token过期失效时
   * 通过调用api 获得企业应用 access_token
   * 并更新数据库中 access_token
   * 
   * @param boolean $isExpired access_token是否过期
   */
  public function getAccessToken($isExpired = false){
    /**
     * access_token 过期失效
     */
    if ($isExpired) {
      $AT_Map = array();
      $AT_Map['corpid'] = self::CORP_ID;
      $AT_Map['corpsecret'] = self::SECRET;

      $access_token_infogroup = $this->httpGet(self::ACCESS_TOKEN_URL . http_build_query($AT_Map),null,null);
      
      $auth = is_null(Auth::where(["access_token" => ["neq","is not null"]])->find()) ? new Auth() : Auth::where(["access_token" => ["neq","is not null"]])->find();
      $info = json_decode($access_token_infogroup,true);

      // 存储新获取access_token 以及 过期时间 access_token_expire
      $auth->access_token = $info["access_token"];
      $auth->access_token_expire = $info["expires_in"] + time();
      $auth->save();

      return $info["access_token"];

    } else {
      /**
       * 检查 access_token_expire 
       * 若小于当前服务器时间 递归本方法 并 $isExpired = true
       */
      if (time() > Auth::where(["access_token" => ["neq","is not null"]])->find()["access_token_expire"]) {
        return self::getAccessToken(true);
      } else {

        // access_token_expire 未过期 数据库取
        return Auth::where(["access_token" => ["neq","is not null"]])->find()["access_token"];
      }
    }
  }

  /**
   * 注：本方法与获取access_token方法一致
   * 
   * 数据库中jsapi_ticket未过期失效时
   * 从数据库中取出jsapi_ticket
   * 
   * 数据库中jsapi_ticket过期失效时
   * 通过调用api 获得企业应用 jsapi_ticket
   * 并更新数据库中 jsapi_ticket
   * 
   * @param boolean $isExpired jsapi_ticket是否过期
   */
  public function get_jsapi_ticket ($isExpired = false) {
    if ($isExpired) {
      $jsapi_ticket_infogroup = $this->httpGet(self::JSAPI_TICKET_URL . http_build_query(["access_token" => self::getAccessToken()]),null,null);
      $auth = is_null(Auth::where(["jsapi_ticket" => ["neq","is not null"]])->find()) ? new Auth() : Auth::where(["jsapi_ticket" => ["neq","is not null"]])->find();
      $info = json_decode($jsapi_ticket_infogroup,true);
      if ($info['errcode'] == 0) {
        $auth->jsapi_ticket = $info["ticket"];
        $auth->jsapi_ticket_expire = $info["expires_in"] + time();
        $auth->save();
        return Tools::ResponseMessages(200,null,"ok",["jsapi_ticket" => $info["ticket"]]);
      }
      return Tools::ResponseMessages(500,"parameter error",null,null);;
    } else {
      if (time() > Auth::where(["jsapi_ticket" => ["neq","is not null"]])->find()["jsapi_ticket_expire"]) {
        return self::get_jsapi_ticket(true);
      } else {
        return Tools::ResponseMessages(200,null,"ok",["jsapi_ticket" => Auth::where(["jsapi_ticket" => ["neq","is not null"]])->find()["jsapi_ticket"]]);
      }
    }
  }
  /**
   * 从本逻辑器中获得URI
   * 
   * @param string $method 操作
   * @param boolean $is_URLencode 是否需URL编码
   * @param array $data 包含数据
   */
  public static function getURL ($method,$is_URLencode,$data) {
    switch ($method) {
      case "base":
      $map['appid'] = self::CORP_ID;
      $map['redirect_uri'] = urlencode(self::REDIRECT_URL);
      $map['response_type'] = 'code';
      if (!is_null($data['is_private'])) {
        /**
         * snsapi_privateinfo 手动授权
         * 
         * snsapi_userinfo 静默授权
         */
        $map['scope'] = $data['is_private'] ? 'snsapi_privateinfo' : 'snsapi_userinfo';
      }
      $map['agentid'] = self::AGENT_ID;
      $map['state'] = myTokenLogic::create_randString();
      return $is_URLencode ? urlencode(self::USER_CODE_URL . http_build_query($map) . "#wechat_redirect") : (self::USER_CODE_URL . http_build_query($map) . "#wechat_redirect");
      break;

      case "index":
      return self::BASE_URL . "index.html?" . is_null($data) ? null : http_build_query($data);
      break;
    }
  }

  /**
   * 通过CODE参数 获取user_ticket
   * 
   * @param $accessToken 企业应用 access_token
   * @param $code 通过redirect_uri 获取到CODE
   */
  public function getTicketByCode($accessToken,$code){
    $UT_Map = array();
    if (!is_null($accessToken) && !is_null($code)) {
      $UT_Map['access_token'] = $accessToken;
      $UT_Map['code'] = $code;

      // BASED ON GET 获取 user_ticket
      $res = json_decode($this->httpGet(self::USER_TICKET_URL . http_build_query($UT_Map),null,null),true);

      switch ($res['errcode']) {

        case 0:
        if (!is_null($res["user_ticket"])) {
          /**
           * api 返回有效user_ticket
           * 
           * 存取用户userid
           */
          session($res['UserId'],[
            "Auth-ticket" => $res["user_ticket"],
            "expire" => $res["expires_in"] + time()
          ],"think");
          if (is_null(User::where("userid",$res['UserId'])->find())) {
            $user = new User();
            $user->userid = $res['UserId'];
            $user->save();
          }
          return Tools::ResponseMessages(self::STATUS_OK,null,"ok",["auth_ticket" => $res["user_ticket"],"UserId" => $res['UserId']]);
        } else {
          return Tools::ResponseMessages(self::NON_STRFF,"非企业成员授权",null,null);
        }
        break;
        // 过期的 access_token
        case self::Expired_ACCESS_TOKEN :
        return self::getTicketByCode(self::getAccessToken(true),$code);
        break;
        // 错误或非法不可用的CODE
        case self::INVALID_CODE :
        return Tools::ResponseMessages(self::INVALID_CODE,$res["errmsg"],null,null);
        break;
        // UNexpected Error
        default:
        return Tools::ResponseMessages(self::QJ_ERROR,"Internal default error",null,null);
        break;
      }
    }
  }

  /**
   * 通过 userid 获取用户信息
   * 
   * @param $access_token 企业应用 access_token
   * @param $userid 用户 userid
   */
  public function getInfoByUserId ($access_token,$userid) {
    if (!is_null($access_token) && !is_null($userid)) {
      $data = array();
      $data['userid'] = $userid;
      $url = self::USER_INFO_URL . "access_token=" . $access_token;
      // BASED ON GET 获得用户信息
      $res = json_decode($this->httpGet($url . "&" . http_build_query($data),null,null,null),true);

      switch ($res["errcode"]) {

        case 0:
        if (is_null(User::where("uname",$res['name'])->find())) {
          if (is_null(User::where("userid",$res['userid'])->find())) {
            $user = new User();
            $user->userid = $res['userid'];
          } else {
            $user = User::where("userid",$res['userid'])->find();
          }
          // 存储用户真实姓名
          $user->uname = $res['name'];
          
          $user->save();
        }
        return Tools::ResponseMessages(self::STATUS_OK,null,"ok",$res);
        break;
        // 过期的 access_token
        case self::Expired_ACCESS_TOKEN :
        return self::getInfoByUserId(self::getAccessToken(true),$userid);
        break;
        // 过期的 user_ticket
        case self::Expired_TICKET :
        return Tools::ResponseMessages(self::Expired_TICKET,"ticket is expired",null,null);
        break;
        // 错误或非法不可用的 user_ticket
        case self::INVALID_TICKET:
        return Tools::ResponseMessages(self::INVALID_TICKET,"ticket is invalid",null,null);
        break;
      }
    } else {
      return Tools::ResponseMessages(self::QJ_ERROR,"Invalid Param",null,null);
    }
  }

  /**
   * CURL Get 方法封装
   * 
   * Idea From 挑战偶像 xxmz & swenb tzapi-client-php
   */
  protected function httpGet($url, $headers = array(), $options = array()){
    return $this->httpRequest($url, $headers, null, 'GET', $options);
  }

  /**
   * CURL Post 方法封装
   * 
   * Idea From 挑战偶像 xxmz & swenb tzapi-client-php
   */
  protected function httpPost($url, $headers = array(), $data=array(), $options = array()){
    return $this->httpRequest($url, $headers, $data, 'POST', $options);
  }

  /**
   * CURL Request 主方法封装
   * 
   * Idea From 挑战偶像 xxmz & swenb tzapi-client-php
   * 
   * 本方法的具体使用事项和流程 欢迎参考 tzapi-client-php
   * 
   * 预留 CURL Post 方法 https 请求接口
   * 由于一些奇怪的错误 最终放弃使用
   */
  protected function httpRequest($url, $headers = array(), $data = array(), $type = 'GET', $options = array())
  {
    $default_options = array(
      'timeout' => 10,
      'connect_timeout' => 3
    );
    $options = array_push($default_options, $options);
    switch ($type) {
      case 'POST':
        curl_setopt($this->cURL_req, CURLOPT_POST, true);
        curl_setopt($this->cURL_req, CURLOPT_POSTFIELDS, '{' . "user_ticket" . $data['user_ticket'] . '}');
        curl_setopt($this->cURL_req, CURLOPT_HTTPHEADER, array(                                                                          
          'Content-Type: application/json',                                                                                
          'Content-Length: ' . strlen(http_build_query($data)))                                                                       
        ); 
        curl_setopt($this->cURL_req, CURLOPT_URL, $url);
        curl_setopt($this->cURL_req, CURLOPT_SSL_VERIFYPEER, false); //这个是重点,规避ssl的证书检查。
        curl_setopt($this->cURL_req, CURLOPT_SSL_VERIFYHOST, false); // 跳过host验证
        break;

      default:
        curl_setopt($this->cURL_req, CURLOPT_CUSTOMREQUEST, $type);
        if (!empty($data)) {
            curl_setopt($this->cURL_req, CURLOPT_URL, $url . $data);
        } else {
            curl_setopt($this->cURL_req, CURLOPT_URL, $url);
        }
        break;
    }

    curl_setopt($this->cURL_req, CURLOPT_TIMEOUT, ceil($options['timeout']));
    curl_setopt($this->cURL_req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->cURL_req, CURLOPT_CONNECTTIMEOUT, ceil($options['connect_timeout']));

    $response = curl_exec($this->cURL_req);
    if (false === $response) {
        throw new RequestException('('.curl_errno($this->cURL_req).') '.curl_error($this->cURL_req));
    }
    return $response;
  }
}
?>