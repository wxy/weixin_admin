weixin_admin
============

微信公众平台的辅助管理脚本集


materials.php
-------------

用于通过海外微信公众管理平台获取微信推送的文章访问量数据。

### 需要的模块

* curl
* mysql

### 使用方式

* 上传文件到WEB，浏览器直接运行，会显示一页（10个消息）的文章的访问数据信息
* 在URL后面加上参数 ?all=1 可以显示全部页的所有文章的访问数据信息
* 也可以在显示一页后，点击页面尾部的链接来显示更多：
  * update other all 显示剩余页面的
  * update all 显示*全部*页面的（包括当前已经显示的第一页）

### 配置

虽然可以直接修改materials.php，但是不建议这样做。可以修改materials_conf.php 其中设置的变量会覆盖materials.php里面全局设置。

主要需要设置的变量有：

* 数据库设置
  * 用于将访问数据记录到数据库中。如果不设置数据库配置，则忽略数据记录的动作。设置数据库，也可以提示两次更新间隔之间的数据变化幅度
* 公众平台的管理员账号
  * 可以将公众平台的管理员账号信息设置写入。如果不设置，则会在运行时通过HTTP认证方式提示输入账号信息，该信息会保持在浏览器内存中，直到关闭浏览器。
* 服务器端用于记录COOKIE的文件夹
  * 由于本脚本实际上是模拟了用户登录行为（通过CURL），所以需要在服务器端支持cookie信息的存储和读取，因此需要设置一个目录可以创建相应的文件创建和访问。
  * 一般这个目录是 /tmp (Linux下)，当然根据你的PHP设置不同，也可能是其它目录，比如上载文件的临时目录。如果不设置，脚本会自动尝试使用上载文件的临时目录。
  
### 数据库存储

如果需要通过数据库存储访问数据，可以通过以下语句创建数据表（或参见database.sql），并如上设置数据库访问信息。

	CREATE TABLE `weixin_article` (
		`appmsgid` int not null default 0,
		`itemidx` int not null default 1,
		`time` date not null default '0000-00-00',
		`img_url` varchar(250) not null default '',
	    `title` varchar(100) not null default '',
		`desc` varchar(200) not null default '',
		`url` varchar(250) not null default '',
		`pageview` int not null default 0,
		`vistor` int not null default 0,
		PRIMARY KEY `id` (`appmsgid`,`itemidx`),
		KEY `time` (`time`),
		KEY `title` (`title`),
		KEY `pageview` (`pageview`),
		KEY `vistor` (`vistor`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;


	
  

