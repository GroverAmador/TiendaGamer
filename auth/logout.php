<?php
// ============================================================
// NexusGear - Logout
// Destroys session and redirects to home
// ============================================================
session_start();
session_unset();
session_destroy();

// Redirect to landing page
header('Location: /nexusgear/index.php');
exit;
?>
