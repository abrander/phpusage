# phpusage
Simple usage logger for php.

Manual logging
--------------
You can simply call ```PhpUsage::logUsage()``` and the class will write
a line to /var/log/phpusage.csv with some basic usage statistics.

Automatic logging
-----------------
For deplying it can be useful to enable automatic logging of all script
execution. The included files *phpusage.ini* can be included in PHP
configuration for automatic logging of all processes. Remember to update
path.

The script *auto_prepend_file.php* will simply call
```register_shutdown_function(array('PhpUsage', 'logUsage'));```.

Fields in the CSV file
----------------------

| Field   | Description                                                            |
| :------ | :--------------------------------------------------------------------- |
| pid     | Process ID of the process                                              |
| start   | Start time of the process as a RFC2822-formatted date                  |
| nice    | Nice value of process                                                  |
| real    | Time passed since process start                                        |
| utime   | Time spend in user mode (excluding time spend by children) (seconds)   |
| stime   | Time spend in kernel mode (excluding time spend by children) (seconds) |
| cutime  | Time spend by children in user mode (seconds)                          |
| cstime  | Time spend by children in kernel mode (seconds)                        |
| rchar   | Characters read by process (bytes)                                     |
| wchar   | Characters written by process (bytes)                                  |
| syscr   | Number of syscalls caused by reads                                     |
| syscw   | Number of syscalls caused by writes                                    |
| iowait  | Time spend waiting for IO (seconds)                                    |
| cmdline | Command line used to start the process                                 |

Importing to MySQL
------------------
The logfile is standard CSV so it should be pretty simple to import to MySQL (or
other SQL server).

```sql
CREATE TABLE phpusage (
	pid int(11),
	start timestamp,
	nice int(11),
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
);

LOAD DATA LOCAL INFILE "/var/log/phpusage.csv"
	INTO TABLE phpusage CHARACTER SET utf8
	FIELDS TERMINATED BY "," ENCLOSED BY "\""
	IGNORE 1 LINES;
```

The file *mysql-import.sql* contains a simple example of importing to MySQL.
