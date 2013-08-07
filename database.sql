	CREATE TABLE `weixin_article` (
		`slave_user` varchar(32) not null default '',
		`appmsgid` int not null default 0,
		`itemidx` int not null default 1,
		`time` date not null default '0000-00-00',
		`img_url` varchar(250) not null default '',
		`title` varchar(100) not null default '',
		`desc` varchar(200) not null default '',
		`url` varchar(250) not null default '',
		`pageview` int not null default 0,
		`vistor` int not null default 0,
		`sent_date` int not null default 0,
		PRIMARY KEY `id` (`slave_user`,`appmsgid`,`itemidx`),
		KEY `time` (`time`),
		KEY `title` (`title`),
		KEY `pageview` (`pageview`),
		KEY `vistor` (`vistor`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
