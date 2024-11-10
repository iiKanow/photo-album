CREATE TABLE `album` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT '相册名称',
    `description` text COMMENT '相册描述',
    `cover_image` varchar(255) DEFAULT NULL COMMENT '封面图片',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `photo` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `album_id` int(11) NOT NULL COMMENT '所属相册ID',
    `filename` varchar(255) NOT NULL COMMENT '文件名',
    `original_name` varchar(255) NOT NULL COMMENT '原始文件名',
    `file_path` varchar(255) NOT NULL COMMENT '文件路径',
    `file_size` int(11) NOT NULL COMMENT '文件大小',
    `mime_type` varchar(100) NOT NULL COMMENT '文件类型',
    `taken_at` datetime DEFAULT NULL COMMENT '拍摄时间',
    `location` varchar(255) DEFAULT NULL COMMENT '拍摄地点',
    `latitude` decimal(10,8) DEFAULT NULL COMMENT '纬度',
    `longitude` decimal(11,8) DEFAULT NULL COMMENT '经度',
    `exif_data` json DEFAULT NULL COMMENT 'EXIF信息',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `album_id` (`album_id`),
    CONSTRAINT `photo_album_id_fk` FOREIGN KEY (`album_id`) REFERENCES `album` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 