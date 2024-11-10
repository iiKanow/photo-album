<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Album extends Model
{
    // 设置表名
    protected $name = 'album';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'title'       => 'string',
        'description' => 'string',
        'storage_path'=> 'string',
        'cover_image' => 'string',
        'create_time' => 'int',
        'update_time' => 'int',
        'delete_time' => 'int'
    ];

    // 关联照片
    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    // 获取封面图片
    public function getCoverPhoto()
    {
        return $this->photos()
            ->where('is_cover', 1)
            ->order('create_time', 'desc')
            ->find() ?? $this->photos()
                ->order('create_time', 'desc')
                ->find();
    }

    // 获取相册统计信息
    public function getPhotoStats()
    {
        $photos = $this->photos;
        $totalSize = 0;
        $latestTime = null;
        
        foreach ($photos as $photo) {
            $totalSize += (int)$photo->file_size;
            if ($latestTime === null || $photo->create_time > $latestTime) {
                $latestTime = $photo->create_time;
            }
        }
        
        return [
            'count' => count($photos),
            'latest' => $latestTime,
            'size' => $totalSize
        ];
    }

    // 获取写入时间
    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    // 获取更新时间
    public function getUpdateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    // 写入时间自动转换
    public function setCreateTimeAttr($value)
    {
        if (is_string($value)) {
            return strtotime($value);
        }
        return $value ?: time();
    }

    // 更新时间自动转换
    public function setUpdateTimeAttr($value)
    {
        if (is_string($value)) {
            return strtotime($value);
        }
        return $value ?: time();
    }
} 