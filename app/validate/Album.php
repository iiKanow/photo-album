<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Album extends Validate
{
    protected $rule = [
        'title'       => 'require|max:100',
        'description' => 'max:500',
    ];

    protected $message = [
        'title.require' => '相册标题不能为空',
        'title.max'     => '相册标题最多不能超过100个字符',
        'description.max' => '相册描述最多不能超过500个字符',
    ];
} 