<?php
function audit_log($conn, $user_id, $action, $details = "") {
    $user_id = (int)$user_id;
    $action  = $conn->real_escape_string($action);
    $details = $conn->real_escape_string($details);

    $sql = "INSERT INTO audit_log (user_id, action, details)
            VALUES ($user_id, '$action', '$details')";
    $conn->query($sql);
}
