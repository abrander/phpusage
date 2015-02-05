# Use like:
# mysql -u user database < mysql-import.sql

DROP TABLE phpusage;

CREATE TABLE phpusage (
	pid int(11),
	start timestamp,
	nice int(11),
	ruid int(11),
	euid int(11),
	`real` float,
	utime float,
	stime float,
	cutime float,
	cstime float,
	rchar int(11),
	wchar int(11),
	syscr int(11),
	syscw int(11),
	iowait float,
	cmdline varchar(100)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

LOAD DATA LOCAL INFILE "/var/log/phpusage.csv" INTO TABLE phpusage CHARACTER SET utf8 FIELDS TERMINATED BY "," ENCLOSED BY "\"" IGNORE 1 LINES;
