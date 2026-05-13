<?php
require 'includes/db.php';
$users = db_query("SELECT id, name, email, plan, trial_ends FROM users");
print_r($users);
$limits = db_query("SELECT * FROM ai_rate_limits");
print_r($limits);
