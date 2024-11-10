<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Photo extends Model
{
    // 设置表名
    protected $name = 'photo';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'album_id' => 'int',
        'file_path' => 'string',
        'relative_path' => 'string',
        'file_size' => 'int',
        'is_cover' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
        'delete_time' => 'int',
        'taken_time' => 'int',
        'camera' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'focal_length' => 'float',
        'aperture' => 'float',
        'iso' => 'int',
        'exposure_time' => 'string',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 软删除
    use \think\model\concern\SoftDelete;
    protected $deleteTime = 'delete_time';

    // 关联相册
    public function album()
    {
        return $this->belongsTo(Album::class);
    }

    // 获取写入时间
    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', (int) $value) : '';
    }

    // 获取更新时间
    public function getUpdateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', (int) $value) : '';
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

    // 添加唯一索引约束
    public function initialize()
    {
        parent::initialize();
        // 如果需要，可以在这里添加唯一索引
        // $this->unique(['file_path', 'album_id']);
    }
}