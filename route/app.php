<?php
use think\facade\Route;

// 首页
Route::get('/', 'Album/index');

// 相册管理
Route::group('album', function () {
    Route::get('/', 'Album/index');
    Route::get('detail/:id', 'Album/detail')->pattern(['id' => '\d+']);
    Route::post('save', 'Album/save');
    Route::get('edit/:id', 'Album/edit');
    Route::post('update/:id', 'Album/update');
    Route::post('delete/:id', 'Album/delete');
    Route::post('upload', 'Album/upload');
    Route::get('search', 'Album/search');
    Route::post('importFolder', 'Album/importFolder');
    Route::post('deletePhoto/:id', 'Album/deletePhoto');
    Route::post('scanDirectory', 'Album/scanDirectory');
    Route::get('image/:id', 'Album/image');
});

// 照片管理
Route::group('photo', function () {
    Route::post('delete/:id', 'Photo/delete');
    Route::get('edit/:id', 'Photo/edit');
    Route::post('update/:id', 'Photo/update');
});

// 调试路由
Route::get('debug/route', function() {
    return json([
        'current_path' => request()->pathinfo(),
        'routes' => Route::getRuleList(),
        'view_path' => config('view.view_path')
    ]);
});
