<?php
session_start();
include "config.php"; // pakai PDO dari config.php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // cek user dari database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND password = :password");
        $stmt->execute([
            ':username' => $username,
            ':password' => $password  // ⚠️ sebaiknya nanti pakai password_hash
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] === "admin") {
                header("Location: dashboard_admin.php");
            } else {
                header("Location: menu.php");
            }
            exit;


            // arahkan sesuai role
            if ($row['role'] === "admin") {
                header("Location: dashboard_admin.php");
            } else {
                header("Location: menu.php");
            }
            exit;
        } else {
            echo "<script>alert('Username atau password salah!'); window.location='index.php';</script>";
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>
