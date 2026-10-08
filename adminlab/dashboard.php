<?php

require_once "../config/app.php";
require_once "../includes/auth.php";

require_role('adminlab');

echo "<h1>Dashboard Admin Lab</h1>";
echo "<p>Login berhasil.</p>";
echo "<p>Selamat datang, " . htmlspecialchars($_SESSION['user']['nama']) . ".</p>";
echo "<p>Role: " . htmlspecialchars($_SESSION['user']['role']) . "</p>";

echo '<a href="../auth/logout.php">Logout</a>';