<?php 
    
use think\Env;

     return [
        'app_status' => Env::get('status','dev')
];