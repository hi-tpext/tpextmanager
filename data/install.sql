CREATE TABLE IF NOT EXISTS `__PREFIX__extension` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `key` varchar(55) NOT NULL DEFAULT '' COMMENT '类名',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '标识',
  `title` varchar(55) NOT NULL DEFAULT '' COMMENT '标题',
  `description` varchar(50) NOT NULL DEFAULT '' COMMENT '介绍',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '类型',
  `install` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否安装',
  `enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE(`key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='扩展信息表';

CREATE TABLE IF NOT EXISTS `__PREFIX__web_config` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `key` varchar(55) NOT NULL DEFAULT '' COMMENT '标识',
  `file` varchar(255) NOT NULL DEFAULT '' COMMENT '文件路径',
  `title` varchar(55) NOT NULL DEFAULT '' COMMENT '标题',
  `config` text COMMENT '配置信息json',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE(`key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='配置表';