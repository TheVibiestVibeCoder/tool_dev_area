<?php
require_once 'user_auth.php';

// Logout user
logoutUser();

// Redirect to login page
redirect('login.php');
