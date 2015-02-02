<?php

require('phpusage.class.php');

// We use register_shutdown_function instead of auto_append_file because
// auto_append_file will NOT execute appended code if the original script
// calls exit()
register_shutdown_function(array('PhpUsage', 'logUsage'));

?>
