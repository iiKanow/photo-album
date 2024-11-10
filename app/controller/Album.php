<?php
namespace app\controller;

use app\model\Album as AlbumModel;
use app\model\Photo as PhotoModel;
use think\facade\View;
use think\facade\Filesystem;
use think\facade\Log;
use think\facade\Db;

class Album extends BaseController
{
    public function index()
    {
        $albums = AlbumModel::order('create_time', 'desc')
            ->where('delete_time', null)
            ->select()
            ->each(function($album) {
                // 获取封面图
                $cover = $album->getCoverPhoto();
                $album->cover_url = $cover ? "/album/image/{$cover->id}" : '';
                
                // 获取统计信息
                $stats = $album->getPhotoStats();
                $album->photo_count = $stats['count'];
                $album->latest_photo = $stats['latest'];
                $album->total_size = $stats['size'];
                
                return $album;
            });
        
        return View::fetch('album/index', ['albums' => $albums]);
    }

    public function detail($id)
    {
        try {
            if (empty($id)) {
                throw new \Exception('相册ID不能为空');
            }

            // 使用 with 预加载，并确保图片不重复
            $album = AlbumModel::with(['photos' => function($query) {
                $query->distinct()->order('create_time', 'desc');
            }])->find($id);

            if (!$album) {
                throw new \Exception('相册不存在');
            }

            // 确保 photos 数据可用
            $photos = $album->photos;
            
            return View::fetch('album/detail', [
                'album' => $album,
                'photos' => $photos
            ]);
        } catch (\Exception $e) {
            Log::error('访问相册失败：' . $e->getMessage());
            return redirect('/album')->with('error', '访问相册失败：' . $e->getMessage());
        }
    }

    /**
     * 保存相册
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            
            // 数据验证
            $validate = validate([
                'name'  => 'require|max:100',
                'storage_path' => 'require',
                'description' => 'max:500'
            ]);

            if (!$validate->check($data)) {
                return json(['code' => 0, 'msg' => $validate->getError()]);
            }

            // 检查目录是否存在且可访问
            if (!is_dir($data['storage_path']) || !is_readable($data['storage_path'])) {
                return json(['code' => 0, 'msg' => '目录不存在或无访问权限']);
            }

            // 创建相册记录
            $album = new AlbumModel;
            $album->title = $data['name'];
            $album->description = $data['description'] ?? '';
            $album->storage_path = $data['storage_path'];
            $album->create_time = time();
            $album->update_time = time();
            
            if ($album->save()) {
                // 扫描目录
                $photoCount = $this->scanDirectory($album->id, $data['storage_path']);
                return json([
                    'code' => 1, 
                    'msg' => "创建成功，已导入 {$photoCount} 张照片",
                    'data' => ['id' => $album->id]
                ]);
            } else {
                return json(['code' => 0, 'msg' => '保存失败']);
            }
        } catch (\Exception $e) {
            Log::error('创建相册失败：' . $e->getMessage());
            return json(['code' => 0, 'msg' => '创建失败：' . $e->getMessage()]);
        }
    }

    /**
     * 扫描目录导入照片
     */
    protected function scanDirectory($albumId, $path)
    {
        $count = 0;
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        try {
            // 使用 RecursiveDirectoryIterator 递归遍历目录
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            // 检查数据库中是否已存在该照片
            $existingPhoto = Db::name('photo')
                ->where('album_id', $albumId)
                ->select()->toArray();
            $existMap = array_column($existingPhoto, 'id', 'file_path');

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, $allowedExtensions)) {
                        $pathName = $file->getPathname();
                        // 检查文件是否为图片
                        if (!getimagesize($pathName)) {
                            continue;
                        }
                        if (isset($existMap[$pathName])) {
                            unset($existMap[$pathName]);
                            continue;
                        }
                        // 获取相对路径
                        $relativePath = str_replace('\\', '/', substr($pathName, strlen($path)));
                        

                        // 获取照片EXIF信息
                        $exif = @exif_read_data($pathName);
                        // $image = new \Imagick($pathName);
                        // // 获取图像的属性
                        // $properties = $image->getImageProperties('*');
                        // dd($properties);
                        // 获取拍摄时间
                        $dateTime = '';
                        if (!empty($exif['DateTimeOriginal'])) {
                            $dateTime = $exif['DateTimeOriginal'];
                        } else if (!empty($exif['FileDateTime'])) {
                            $dateTime = $exif['FileDateTime'];
                        }
                        
                        // 获取相机信息
                        $camera = '';
                        if (!empty($exif['Make'])) {
                            $camera = $exif['Make'];
                            if (!empty($exif['Model'])) {
                                $camera .= ' ' . $exif['Model'];
                            }
                        }
                        
                        // 获取GPS信息
                        $latitude = null;
                        $longitude = null;
                        if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLatitudeRef'])) {
                            $latitude = $this->getGPSCoordinate($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                        }
                        if (!empty($exif['GPSLongitude']) && !empty($exif['GPSLongitudeRef'])) {
                            $longitude = $this->getGPSCoordinate($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
                        }

                        // 获取其他EXIF信息
                        $focalLength = !empty($exif['FocalLength']) ? $exif['FocalLength'] : null;
                        $aperture = !empty($exif['COMPUTED']['ApertureFNumber']) ? $exif['COMPUTED']['ApertureFNumber'] : null;
                        $iso = !empty($exif['ISOSpeedRatings']) ? $exif['ISOSpeedRatings'] : null;
                        $exposureTime = !empty($exif['ExposureTime']) ? $exif['ExposureTime'] : null;
                        
                        // 准备数据库字段
                        $photoData = [
                            'album_id' => $albumId,
                            'file_path' => $pathName,
                            'relative_path' => $relativePath,
                            'file_size' => $file->getSize(),
                            'is_cover' => 0,
                            'create_time' => time(),
                            'update_time' => time(),
                            'taken_time' => $dateTime ?? null,
                            'camera' => $camera,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'focal_length' => $focalLength,
                            'aperture' => $aperture,
                            'iso' => $iso,
                            'exposure_time' => $exposureTime
                        ];
                        $photo = new PhotoModel($photoData);
                        $photo->save();
                        $count++;
                    }
                }
            }

            // 清理不存在的照片记录
            $delIds = array_values($existMap);
            if ($delIds) {
                Db::name('photo')
                    ->where('album_id', $albumId)
                    ->whereIn('id', $delIds)
                    ->delete();
            }
            return $count;
        } catch (\Exception $e) {
            Log::error('扫描目录失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取相册信息
     */
    public function edit($id)
    {
        try {
            $album = AlbumModel::find($id);
            if (!$album) {
                return json(['code' => 0, 'msg' => '相册不存在']);
            }
            
            // 获取统计信息
            $stats = $album->getPhotoStats();
            
            return json([
                'code' => 1, 
                'data' => [
                    'id' => $album->id,
                    'name' => $album->title,
                    'storage_path' => $album->storage_path,
                    'description' => $album->description,
                    'stats' => [
                        'photo_count' => $stats['count'],
                        'latest_photo' => $stats['latest'],
                        'total_size' => $stats['size']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取相册信息失败：' . $e->getMessage());
            return json(['code' => 0, 'msg' => '获取相册信息失败：' . $e->getMessage()]);
        }
    }

    /**
     * 更新相册信息
     */
    public function update($id)
    {
        try {
            $album = AlbumModel::find($id);
            if (!$album) {
                return json(['code' => 0, 'msg' => '相册不存在']);
            }

            $data = input('post.');
            
            // 验证输入
            $validate = validate([
                'name'  => 'require|max:100',
                'storage_path' => 'require',
                'description' => 'max:500',
            ]);

            if (!$validate->check($data)) {
                return json(['code' => 0, 'msg' => $validate->getError()]);
            }

            // 开启事务
            Db::startTrans();
            try {
                // 更新基本信息
                $album->title = $data['name'];
                $album->description = $data['description'] ?? '';
                
                // 如果修改了存储路径
                if (!empty($data['storage_path']) && $data['storage_path'] !== $album->storage_path) {
                    // 检查新目录
                    if (!is_dir($data['storage_path']) || !is_readable($data['storage_path'])) {
                        throw new \Exception('目录不存在或无访问权限');
                    }
                    
                    // 删除原有照片记录
                    Db::name('photo')->where('album_id', $album->id)->delete();
                    
                    // 更新存储路径
                    $album->storage_path = $data['storage_path'];
                }
                    
                // 重新扫描目录
                $photoCount = $this->scanDirectory($album->id, $data['storage_path']);
                
                $album->save();
                
                Db::commit();
                
                return json([
                    'code' => 1, 
                    'msg' => isset($photoCount) ? "更新成功，已导入 {$photoCount} 张照片" : '更新成功'
                ]);
                
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('更新相册失败：' . $e->getMessage());
            return json(['code' => 0, 'msg' => '更新失败：' . $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        try {
            $album = AlbumModel::find($id);
            if (!$album) {
                return json(['code' => 0, 'msg' => '相册不存在']);
            }

            foreach ($album->photos as $photo) {
                if (file_exists(public_path() . $photo->file_path)) {
                    unlink(public_path() . $photo->file_path);
                }
                $photo->delete();
            }
            
            $album->delete();
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    /**
     * 上传照片
     */
    public function upload()
    {
        try {
            $files = request()->file('photos');
            $albumId = input('post.album_id', 0);
            
            if (empty($files)) {
                return json(['code' => 1, 'msg' => '没有接收到上传文件']);
            }

            // 获取相册信息
            $album = AlbumModel::find($albumId);
            if (!$album || empty($album->storage_path)) {
                return json(['code' => 1, 'msg' => '相册不存在或未设置存储路径']);
            }

            // 确保 $files 是数组
            if (!is_array($files)) {
                $files = [$files];
            }

            $successFiles = [];
            
            foreach ($files as $file) {
                // 验证文件
                validate(['file' => [
                    'fileSize' => 838860800,
                    'fileExt' => 'jpg,jpeg,png,gif'
                ]])->check(['file' => $file]);
                
                // 生成存储路径
                $savePath = $album->storage_path . '/uploads/' . date('Ymd');
                if (!is_dir($savePath)) {
                    mkdir($savePath, 0755, true);
                }
                
                // 生成文件名
                $fileName = md5_file($file->getPathname()) . '.' . $file->getOriginalExtension();
                $fullPath = $savePath . '/' . $fileName;
                
                // 移动文件
                if ($file->move($savePath, $fileName)) {
                    // 保存到数据库
                    $photo = new PhotoModel;
                    $photo->album_id = $albumId;
                    $photo->file_path = $fullPath;
                    $photo->relative_path = '/uploads/' . date('Ymd') . '/' . $fileName;
                    $photo->save();
                    
                    $successFiles[] = [
                        'url' => $fullPath,  // 返回完整路径
                        'name' => $file->getOriginalName(),
                        'photo_id' => $photo->id
                    ];
                }
            }
            
            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => $successFiles
            ]);
            
        } catch (\Exception $e) {
            Log::error('上传失败：' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => '上传失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 删除照片
     * @param int $id 照片ID
     */
    public function deletePhoto($id)
    {
        try {
            // 查找照片
            $photo = PhotoModel::find($id);
            if (!$photo) {
                return json(['code' => 1, 'msg' => '照片不存在']);
            }

            // 删除物理文件
            $filePath = public_path() . $photo->file_path;
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // 从数据库中删除记录
            $photo->delete();

            return json(['code' => 0, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            Log::error('删除照片失败：' . $e->getMessage());
            return json(['code' => 1, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    private function getGPSCoordinate($coordinate, $ref)
    {
        $degrees = count($coordinate) > 0 ? $this->gpsToNum($coordinate[0]) : 0;
        $minutes = count($coordinate) > 1 ? $this->gpsToNum($coordinate[1]) : 0;
        $seconds = count($coordinate) > 2 ? $this->gpsToNum($coordinate[2]) : 0;
        
        $flip = ($ref == 'W' || $ref == 'S') ? -1 : 1;
        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    private function gpsToNum($coordPart)
    {
        $parts = explode('/', $coordPart);
        if (count($parts) <= 0) return 0;
        if (count($parts) == 1) return $parts[0];
        return floatval($parts[0]) / floatval($parts[1]);
    }

    /**
     * 递归扫描目录中的照片
     * @param string $directory 目录路径
     * @param int $albumId 相册ID
     * @return int 导入的照片数量
     */
    private function scanPhotosInDirectory($directory, $albumId)
    {
        $photoCount = 0;
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        try {
            // 获取数据库中已有的照片
            $existingPhotos = PhotoModel::where('album_id', $albumId)->get()->keyBy('file_path');

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, $allowedExtensions)) {
                        // 获取相对路径
                        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($directory)));

                        // 检查数据库中是否存在该照片
                        if (isset($existingPhotos[$file->getPathname()])) {
                            // 照片已存在，更新信息
                            $photo = $existingPhotos[$file->getPathname()];
                            $photo->relative_path = $relativePath; // 更新相对路径（如果需要）
                            $photo->save();
                        } else {
                            // 照片不存在，新增
                            $photo = new PhotoModel();
                            $photo->album_id = $albumId;
                            $photo->file_path = $file->getPathname();  // 保存完整路径
                            $photo->relative_path = $relativePath;     // 保存相对路径
                            $photo->save();
                            $photoCount++;
                        }
                    }
                }
            }

            // 删除数据库中存在但在目录中不存在的照片
            foreach ($existingPhotos as $existingPhoto) {
                if (!file_exists($existingPhoto->file_path)) {
                    $existingPhoto->delete(); // 删除数据库记录
                    Log::info('删除照片录：' . $existingPhoto->file_path);
                }
            }

            return $photoCount;

        } catch (\Exception $e) {
            Log::error('扫描照片失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取图片
     */
    public function image($id)
    {
        try {
            $photo = PhotoModel::find($id);
            if (!$photo || !file_exists($photo->file_path)) {
                return response('', 404);
            }

            // 取文件MIME类型
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $photo->file_path);
            finfo_close($finfo);

            // 读取文件内容
            $content = file_get_contents($photo->file_path);
            
            // 返回文内容
            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'public, max-age=31536000'
            ]);
        } catch (\Exception $e) {
            Log::error('获取图片失败：' . $e->getMessage());
            return response('', 500);
        }
    }
} 