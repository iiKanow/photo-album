<?php
// 应用公共文件

if (!function_exists('format_bytes')) {
    /**
     * 格式化文件大小
     * @param int $bytes 字节数
     * @param int $decimals 小数位数
     * @return string
     */
    function format_bytes($bytes, $decimals = 2)
    {
        if ($bytes === null) {
            return '0 B';
        }
        
        $bytes = (int)$bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $decimals) . ' ' . $units[$pow];
    }
}
