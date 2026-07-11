<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'app/Config/Database.php';
require 'app/Helpers/ResponseHelper.php';
require 'app/Helpers/AuthHelper.php';
require 'app/Controllers/AdminController.php';

$_GET['id'] = 3;
(new App\Controllers\AdminController())->handleAdminGetSubmissionContent();
