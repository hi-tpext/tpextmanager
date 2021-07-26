CREATE TABLE IF NOT EXISTS `__PREFIX__table_relation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `relation_name` varchar(55) NOT NULL DEFAULT '' COMMENT '关联名称',
  `local_table_name` varchar(55) NOT NULL DEFAULT '' COMMENT '主表名称',
  `local_key` varchar(55) NOT NULL DEFAULT '' COMMENT '关联主键',
  `foreign_table_name` varchar(55) NOT NULL DEFAULT '' COMMENT '外表名称',
  `relation_type` varchar(20) NOT NULL DEFAULT '' COMMENT '关联类型',
  `foreign_key` varchar(55) NOT NULL DEFAULT '' COMMENT '关联外键',
  `create_time` datetime NOT NULL DEFAULT '2021-07-23 23:22:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2021-07-23 23:22:00' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='表间关联';