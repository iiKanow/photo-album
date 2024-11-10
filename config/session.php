<?php
// +----------------------------------------------------------------------
// | 会话设置
// +----------------------------------------------------------------------

return [
    // session name
    'name'           => 'PHPSESSID',
    // SESSION_ID的提交变量,解决flash上传跨域
    'var_session_id' => '',
    // 驱动方式 支持 file redis memcache memcached
    'type'           => 'file',
    // 存储连接标识 当type使用redis/memcache/memcached驱动时有效
    'store'          => null,
    // 过期时间
    'expire'         => 1440,
    // 前缀
    'prefix'         => '',
    // 是否自动开启 SESSION
    'auto_start'     => true,
];
