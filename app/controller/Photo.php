<?php
namespace app\controller;

use app\model\Photo as PhotoModel;
use think\facade\View;

class Photo extends BaseController
{
    public function delete($id)
    {
        try {
            $photo = PhotoModel::find($id);
            if (!$photo) {
                return json(['code' => 0, 'msg' => '照片不存在']);
            }
            
            // 删除物理文件
            $filePath = public_path() . ltrim($photo->file_path, '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $photo->delete();
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $photo = PhotoModel::find($id);
        if (!$photo) {
            return json(['code' => 0, 'msg' => '照片不存在']);
        }
        return json(['code' => 1, 'data' => $photo]);
    }

    public function update($id)
    {
        try {
            $photo = PhotoModel::find($id);
            if (!$photo) {
                return json(['code' => 0, 'msg' => '照片不存在']);
            }
            
            $data = input('post.');
            
            // 验证数据
            $validate = validate([
                'title' => 'max:100',
                'description' => 'max:500',
            ], [
                'title.max' => '照片标题最多100个字符',
                'description.max' => '照片描述最多500个字符',
            ]);

            if (!$validate->check($data)) {
                return json(['code' => 0, 'msg' => $validate->getError()]);
            }

            $photo->title = $data['title'] ?? $photo->title;
            $photo->description = $data['description'] ?? $photo->description;
            $photo->save();
            
            return json(['code' => 1, 'msg' => '更新成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '更新失败：' . $e->getMessage()]);
        }
    }
} 