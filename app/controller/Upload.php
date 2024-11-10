<?php

namespace app\controller;

use think\facade\Request;

class Upload
{
    public function upload()
    {
        try {
            // 上传逻辑...
            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => [
                    'url' => $fileUrl,  // 图片访问地址
                    'name' => $fileName // 文件名
                ]
            ]);
        } catch (\Exception $e) {
            // 错误处理...
        }
    }
} 