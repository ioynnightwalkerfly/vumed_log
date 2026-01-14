<?php 
if ($user['role'] !== 'admin') {
    header("Location: ../public/index.php?error=เฉพาะผู้ดูแลระบบเท่านั้น");
    exit;
}

?>