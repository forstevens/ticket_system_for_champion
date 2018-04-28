<?php
namespace app\index\Logic;

use think\Model;

class LogicTools extends Model {
  public static function array2htmlspecailchars($data){
    $res = array();
    foreach($data as $k => $v){
      $res[$k] = htmlspecialchars($v);
    }
    return $res;
  }
  public static function ResponseMessages ($status,$err_msg,$msg,$extraData) {
    if (!is_null($status)) {
      $res["status"] = $status;
    }
    if (!is_null($err_msg)) {
      $res['err_msg'] = $err_msg;
    }
    if (!is_null($msg)) {
      $res['msg'] = $msg;
    }
    if (!is_null($extraData)) {
      if (!array_key_exists("status",$extraData)) {
        $res['status'] = $status;
      }
      foreach ($extraData as $k => $v) {
        if (!is_null($v)) {
          $res[$k] = $v;
        }
      }
    }
    
    return $res;
  }
  /**
   * function for creating random string
   * @param int $length
   * @return string $code ==myRandomString
   */
  public static function createRandomString($length=16){
    $stringRoot = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_';
    $code = '';
    for($i = 0; $i < $length; $i++){
        $code .= $stringRoot[random_int(0,strlen($stringRoot)-1)];
    }
    return $code;
  }
}
?>