<!-- NexusGear - Toast container -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>
<?php
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    $type  = $_SESSION['flash_type'] ?? 'info';
    echo '<script>document.addEventListener("DOMContentLoaded",()=>showToast('.json_encode($flash).','.json_encode($type).'));</script>';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>
