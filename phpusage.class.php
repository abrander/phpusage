<?php

/**
 * A few helper functions to collect usage statistics about a process.
 * Can be used simply by calling PhpUsage::logUsage(), which will write
 * a line to a CSV file with current process statistics.
 * @license MIT
 * @copyright 2015 Brander & Birkedal ApS
 * @author Anders Brander <anders@brander.dk>
 */
class PhpUsage {
	// Check that this matches by "$ getconf CLK_TCK"
	const CLK_TCK = 100;

	// Where should be put the output from logUsage()
	const logfile = '/var/log/phpusage.csv';

	const uptimeFormat = '%f %f';
	const uptimePath = '/proc/uptime';

	// This is a simplified format understood by php
	const statFormat = '%d %s %c %d %d %d %d %d %u %u %u %u %u %u %u %d %d %d %d %d %d %u %u %d %u %u %u %u %u %u %u %u %u %u %u %u %u %d %d %u %u %u %u %d %u %u %u %u %u %u %u %d';

	/**
	 * Read uptime from /proc
	 * @return float|FALSE The number of seconds since system boot
	 */
	public static function readUptime() {
		$content = file_get_contents(self::uptimePath);

		if ($content === FALSE) {
			// file_get_contents() should generate an error if needed, just
			// return
			return FALSE;
		}

		$read = sscanf($content, self::uptimeFormat, $uptime, $idle);

		if ($read != 2) {
			error_log("Could not understand '{self::uptimePath}'");
			return FALSE;
		}

		return $uptime;
	}

	/**
	 * Read process stat information from /proc
	 * @param $pid integer The PID to return stats for, setting this to 0 will
	 *                     read current process stat.
	 * @return array|FALSE An associative array of values read. The number of
	 *                     values returned will depend on the kernel version.
	 *                     Returns FALSE on error
	 */
	public static function readStat($pid) {
		if ($pid == 0)
			$pid = 'self';

		$content = file_get_contents("/proc/${pid}/stat");

		if ($content === FALSE) {
			return FALSE;
		}

		$pid = 0;
		$stat = [];

		// Read format as defined in do_task_stat():
		// http://lxr.free-electrons.com/source/fs/proc/array.c#L371
		$read = sscanf($content, self::statFormat,
			$stat['pid'],
			$stat['comm'],
			$stat['state'],
			$stat['ppid'],
			$stat['pgrp'],
			$stat['session'],
			$stat['tty_nr'],
			$stat['tpgid'],
			$stat['flags'],
			$stat['minflt'],
			$stat['cminflt'],
			$stat['majflt'],
			$stat['cmajflt'],
			$stat['utime'],
			$stat['stime'],
			$stat['cutime'],
			$stat['cstime'],
			$stat['priority'], // 2.6
			$stat['nice'],
			$stat['num_threads'], // 2.6
			$stat['itrealvalue'], // Zero since 2.6.17
			$stat['starttime'], // 2.6
			$stat['vsize'],
			$stat['rss'],
			$stat['rsslim'],
			$stat['startcode'],
			$stat['endcode'],
			$stat['startstack'],
			$stat['kstkesp'],
			$stat['kstkeip'],
			$stat['signal'],
			$stat['blocked'],
			$stat['sigignore'],
			$stat['sigcatch'],
			$stat['wchan'],
			$stat['nswap'], // deprecated
			$stat['cnswap'],  // deprecated
			$stat['exit_signal'], // 2.1.22
			$stat['processor'], // 2.2.8
			$stat['rt_priority'], // 2.5.19
			$stat['policy'], // 2.5.19
			$stat['delayacct_blkio_ticks'], // 2.6.18
			$stat['guest_time'], // 2.6.24
			$stat['cguest_time'], // 2.6.24
			$stat['start_data'], // 3.3
			$stat['end_data'], // 3.3
			$stat['start_blk'], // 3.3
			$stat['arg_start'], // 3.5
			$stat['arg_end'], // 3.5
			$stat['env_start'], // 3.5
			$stat['env_end'], // 3.5
			$stat['exit_code'] // 3.5
		);

		if ($read < 42) {
			error_log("Could not understand the format of /proc/<pid>/stat");
			return FALSE;
		}

		// Remove unknown results from array
		array_splice($stat, $read, 52 - $read);

		return $stat;
	}

	/**
	 * Read IO statistics for a specific process from /proc
	 * @param $pid integer The PID to return stats for, setting this to 0 will
	 *                     read current process stat.
	 * @return array|FALSE An associative array of values read. Returns FALSE
	 *                     on error.
	 */
	public static function readIo($pid) {
		if ($pid == 0)
			$pid = 'self';

		// Content described here:
		// http://lxr.free-electrons.com/source/include/linux/task_io_accounting.h
		$content = file_get_contents("/proc/${pid}/io");

		if ($content === FALSE) {
			return FALSE;
		}

		$lines = explode("\n", trim($content));

		$io = [];
		foreach ($lines as $line) {
			$parts = explode(": ", $line);
			$io[$parts[0]] = $parts[1];
		}

		return $io;
	}

	/**
	 * Read command line
	 * @param $pid integer The PID to return stats for, setting this to 0 will
	 *                     read current process stat.
	 * @return string|FALSE The command line used for starting this process.
	 *                      Please note that this is NOT escaped and is only
	 *						useful for logging.
	 */
	public static function readSched($pid) {
		if ($pid == 0)
			$pid = 'self';

		$content = file_get_contents("/proc/${pid}/sched");

		if ($content === FALSE) {
			return FALSE;
		}

		$sched = [];

		$lines = explode("\n", $content);
		foreach ($lines as $line) {
			if (preg_match("/(?P<key>\w+)\s+:\s+(?P<value>[\d.]+)/", $line, $matches) === 1) {
				$sched[$matches['key']] =
					(strpos($matches['value'], ".") !== false)
						? floatval($matches['value'])
						: intval($matches['value']);
			}
		}

		return $sched;
	}

	/**
	 * Read command line paramaters
	 * @param $pid integer The PID to return stats for, setting this to 0 will
	 *                     read current process stat.
	 * @return string|FALSE The command line used for starting this process.
	 *                      Please note that this is NOT escaped and is only
	 *						useful for logging.
	 * @info 
	 */
	public static function readCmdLine($pid) {
		if ($pid == 0)
			$pid = 'self';

		// Content described here:
		// http://lxr.free-electrons.com/source/include/linux/task_io_accounting.h
		$content = file_get_contents("/proc/${pid}/cmdline");

		if ($content === FALSE) {
			return FALSE;
		}

		$cmdline = implode(' ', explode("\x00", trim($content)));

		return $cmdline;
	}

	/**
	 * Calculate common statistics for a specific process
	 * @param $pid integer The PID to return stats for, setting this to 0 will
	 *                     read current process stat.
	 * @return array|FALSE An associative array of values. Returns FALSE
	 *                     on error.
	 */
	public static function calculateStats($pid) {
		$stats = [];
		$uptime = self::readUptime();

		$cmdline = self::readCmdLine($pid);
		$stat = self::readStat($pid);
		$io = self::readIo($pid);
		$sched = self::readSched($pid);

		if (!$uptime)
			return FALSE;

		if (!$cmdline)
			return FALSE;

		if (!$stat)
			return FALSE;

		if (!$io)
			return FALSE;

		if (!$sched)
			return FALSE;

		$stats['pid'] = $stat['pid'];
		$stats['start'] = date('c', time() - ($uptime - ($stat['starttime'] / self::CLK_TCK)));
		$stats['nice'] = $stat['nice'];
		$stats['real'] = $uptime - ($stat['starttime'] / self::CLK_TCK);
		$stats['utime'] = $stat['utime'] / self::CLK_TCK;
		$stats['stime'] = $stat['stime'] / self::CLK_TCK;
		$stats['cutime'] = $stat['cutime'] / self::CLK_TCK;
		$stats['cstime'] =  $stat['cstime'] / self::CLK_TCK;
		$stats['rchar'] = $io['rchar'];
		$stats['wchar'] = $io['wchar'];
		$stats['syscr'] = $io['syscr'];
		$stats['syscw'] = $io['syscw'];
		$stats['iowait'] = $sched['iowait_sum'] / 1000.0;
		$stats['cmdline'] = $cmdline;

		return $stats;
	}

	/**
	 * Log the script resource usage to CSV file
	 */
	public static function logUsage() {
		$stats = self::calculateStats(0);

		if (!$stats) {
			error_log("Failed to aquire needed statistics for " . __CLASS__);
			return;
		}

		$handle = fopen(self::logfile, 'a', FALSE);
		if (!$handle) {
			// We rely on fopen() generating appropiate errors, just return
			return;
		}

		if (flock($handle, LOCK_EX)) {
			// If we got an empty file, write a CSV header line
			$fstat = fstat($handle);
			if ($fstat['size'] === 0) {
				fputcsv($handle, array_keys($stats));
			}

			fputcsv($handle, $stats);
			fflush($handle);
			flock($handle, LOCK_UN);
		} else {
			error_log("Failed to aquire lock for {self::logfile}");
		}

		// No need to check this for errors, we already flushed
		fclose($handle);
	}
}

?>
