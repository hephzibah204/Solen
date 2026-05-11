<?php
require 'includes/db.php';
get_db()->query("DELETE FROM settings WHERE key='ai_provider'");
echo 'Deleted';
