<?php

require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user'])) {

    header("Location: login.php");
    exit;

}


/* =========================================================
   CEK ADMIN
========================================================= */

if (
    !isset($_SESSION['user']['role']) ||
    $_SESSION['user']['role'] !== 'admin'
) {

    http_response_code(403);

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>

            <h1>403</h1>

            <h3>Akses Ditolak</h3>

            <p>
                Halaman ini hanya dapat diakses administrator GosDrive.
            </p>
        </div>
    ");

}


/* =========================================================
   AUTO SUSPEND USER TIDAK AKTIF 3 BULAN
========================================================= */

/*
   Hanya user biasa yang diproses.

   Admin TIDAK akan otomatis suspended.
*/


$stmtSuspend = $pdo->prepare("

    UPDATE users

    SET status = 'suspended'

    WHERE role = 'user'

    AND status = 'active'

    AND (

        (
            last_login IS NOT NULL

            AND last_login <=
                DATE_SUB(
                    NOW(),
                    INTERVAL 3 MONTH
                )
        )

        OR

        (
            last_login IS NULL

            AND created_at <=
                DATE_SUB(
                    NOW(),
                    INTERVAL 3 MONTH
                )
        )

    )

");

$stmtSuspend->execute();


/* =========================================================
   AKSI ADMIN
========================================================= */

$message = '';
$messageType = '';


if (
    isset($_POST['action']) &&
    isset($_POST['user_id'])
) {

    $action =
        $_POST['action'];

    $targetUserId =
        (int)$_POST['user_id'];


    /* =====================================================
       JANGAN BOLEHKAN ADMIN MENSUSPEND DIRINYA SENDIRI
    ===================================================== */

    if (
        $targetUserId ===
        (int)$_SESSION['user']['id']
    ) {

        $message =
            "Anda tidak dapat mengubah status akun admin sendiri.";

        $messageType =
            "danger";

    }

    else {


        /* =================================================
           SUSPEND
        ================================================= */

if ($action === 'suspend') {

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='suspended'
        WHERE id=?
        AND role='user'
    ");

    $stmt->execute([$targetUserId]);

    if($stmt->rowCount()){

        $message="User berhasil disuspend.";
        $messageType="success";

    }else{

        $message="User tidak ditemukan.";
        $messageType="warning";

    }

}

elseif ($action === 'activate') {

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='active'
        WHERE id=?
        AND role='user'
    ");

    $stmt->execute([$targetUserId]);

    if($stmt->rowCount()){

        $message="User berhasil diaktifkan.";
        $messageType="success";

    }else{

        $message="User tidak ditemukan.";
        $messageType="warning";

    }

}

elseif ($action === 'reset') {

    $defaultPassword = '123456';

    $passwordHash = password_hash(
        $defaultPassword,
        PASSWORD_DEFAULT
    );

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?
        WHERE id = ?
        AND role = 'user'
    ");

    $stmt->execute([
        $passwordHash,
        $targetUserId
    ]);

    if ($stmt->rowCount() > 0) {

        $message = "Password berhasil direset ke 123456.";

        $messageType = "success";

    } else {

        $message = "User tidak ditemukan.";

        $messageType = "warning";

    }

}


        /* =================================================
           AKTIFKAN
        ================================================= */

        elseif ($action === 'activate') {

            $stmt =
                $pdo->prepare("

                    UPDATE users

                    SET status = 'active'

                    WHERE id = ?

                    AND role = 'user'

                ");

            $stmt->execute([
                $targetUserId
            ]);


            if ($stmt->rowCount() > 0) {

                $message =
                    "User berhasil diaktifkan kembali.";

                $messageType =
                    "success";

            }

            else {

                $message =
                    "User tidak ditemukan atau bukan user biasa.";

                $messageType =
                    "warning";

            }

        }

    }

}

/* =========================================================
   STATISTIK USER
========================================================= */

$stmtTotal =
    $pdo->query("

        SELECT COUNT(*)

        FROM users

    ");

$totalUsers =
    (int)$stmtTotal->fetchColumn();


$stmtActive =
    $pdo->query("

        SELECT COUNT(*)

        FROM users

        WHERE status = 'active'

    ");

$activeUsers =
    (int)$stmtActive->fetchColumn();


$stmtSuspended =
    $pdo->query("

        SELECT COUNT(*)

        FROM users

        WHERE status = 'suspended'

    ");

$suspendedUsers =
    (int)$stmtSuspended->fetchColumn();


/* =========================================================
   AMBIL USER
========================================================= */

$stmtUsers =
    $pdo->query("

        SELECT
            id,
            username,
            role,
            status,
            last_login,
            created_at,
            storage_used,
            storage_quota

        FROM users

        ORDER BY

            CASE

                WHEN role = 'admin'
                THEN 0

                ELSE 1

            END,

            username ASC

    ");

$users =
    $stmtUsers->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   FORMAT TANGGAL
========================================================= */

function formatDateAdmin($date)
{

    if (
        empty($date) ||
        $date === '0000-00-00 00:00:00'
    ) {

        return 'Belum pernah login';

    }


    return date(
        'd-m-Y H:i',
        strtotime($date)
    );

}


/* =========================================================
   HITUNG TIDAK AKTIF
========================================================= */

function inactivityText($lastLogin)
{

    if (empty($lastLogin)) {

        return 'Belum pernah login';

    }


    $last =
        new DateTime($lastLogin);

    $now =
        new DateTime();

    $diff =
        $last->diff($now);


    if ($diff->y > 0) {

        return
            $diff->y .
            ' tahun ' .
            $diff->m .
            ' bulan';

    }


    if ($diff->m > 0) {

        return
            $diff->m .
            ' bulan ' .
            $diff->d .
            ' hari';

    }


    if ($diff->d > 0) {

        return
            $diff->d .
            ' hari';

    }


    if ($diff->h > 0) {

        return
            $diff->h .
            ' jam';

    }


    return 'Baru saja';

}


/* =========================================================
   FORMAT STORAGE
========================================================= */

function formatStorageAdmin($bytes)
{

    $bytes =
        (int)$bytes;


    if ($bytes <= 0) {

        return '0 B';

    }


    $units = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB'
    ];


    $i =
        floor(
            log($bytes, 1024)
        );


    $i =
        min(
            $i,
            count($units) - 1
        );


    return
        round(
            $bytes /
            pow(1024, $i),
            2
        )
        . ' '
        . $units[$i];

}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>GosDrive Admin</title>


<!-- BOOTSTRAP -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- FONT AWESOME -->

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
>


<style>

/* =========================================================
   GLOBAL
========================================================= */

body {

    margin:0;

    background:#f4f6f9;

    font-family:
        'Segoe UI',
        sans-serif;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position:fixed;

    left:0;

    top:0;

    width:240px;

    height:100vh;

    background:#fff;

    padding:25px 20px;

    box-shadow:
        2px 0 10px
        rgba(0,0,0,.06);

    z-index:1000;

}


.sidebar-title {

    font-size:24px;

    font-weight:700;

    margin-bottom:5px;

}


.sidebar-subtitle {

    font-size:13px;

    color:#888;

    margin-bottom:30px;

}


.sidebar a {

    display:block;

    padding:11px 13px;

    margin-bottom:7px;

    border-radius:10px;

    text-decoration:none;

    color:#555;

    transition:.2s;

}


.sidebar a:hover {

    background:#eef2ff;

    color:#4f46e5;

}


.sidebar a i {

    width:25px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left:240px;

    padding:30px;

}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:25px;

}


.topbar h3 {

    margin:0;

    font-weight:600;

}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card {

    background:#fff;

    border-radius:15px;

    padding:22px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.05);

    height:100%;

}


.stat-icon {

    width:48px;

    height:48px;

    border-radius:12px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:21px;

    margin-bottom:15px;

}


.stat-number {

    font-size:30px;

    font-weight:700;

}


.stat-label {

    color:#777;

    font-size:14px;

}


/* =========================================================
   USER CARD
========================================================= */

.user-card {

    background:#fff;

    border-radius:15px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.05);

    overflow:hidden;

}


/* =========================================================
   TABLE
========================================================= */

.table {

    margin-bottom:0;

}


.table th {

    white-space:nowrap;

    font-size:13px;

    color:#666;

}


.table td {

    vertical-align:middle;

}


/* =========================================================
   USERNAME
========================================================= */

.username {

    font-weight:600;

}


.role-admin {

    font-size:11px;

    background:#212529;

    color:#fff;

    padding:3px 7px;

    border-radius:20px;

}


/* =========================================================
   STATUS
========================================================= */

.status-active {

    background:#d1e7dd;

    color:#0f5132;

    padding:5px 10px;

    border-radius:20px;

    font-size:12px;

    font-weight:600;

}


.status-suspended {

    background:#f8d7da;

    color:#842029;

    padding:5px 10px;

    border-radius:20px;

    font-size:12px;

    font-weight:600;

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:768px){

    .sidebar {

        position:relative;

        width:100%;

        height:auto;

    }


    .main {

        margin-left:0;

        padding:15px;

    }


    .topbar {

        display:block;

    }


    .topbar .btn {

        margin-top:10px;

    }


    .user-card {

        overflow-x:auto;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">
    <div class="sidebar-subtitle">
        Administrator Panel
    </div>


    <a href="admin.php">
        <i class="fa fa-gauge"></i>
        Dashboard Admin
    </a>
    <hr>


    <a
        href="admin_logout.php"
        class="text-danger"
        onclick="
            return confirm(
                'Apakah Anda yakin ingin logout?'
            );
        "
    >

        <i class="fa fa-sign-out-alt"></i>

        Logout

    </a>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <!-- =================================================
         TOP BAR
    ================================================== -->

    <div class="topbar">

        <div>

            <h3>

                GosDrive Admin

            </h3>

            <small class="text-muted">

                Selamat datang,
                <?= htmlspecialchars(
                    $_SESSION['user']['username']
                ) ?>

            </small>

        </div>


        <div>

            <span class="badge bg-dark">

                <i class="fa fa-user-shield"></i>

                ADMIN

            </span>

        </div>

    </div>


    <!-- =================================================
         ALERT
    ================================================== -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-<?= $messageType ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         STATISTIK
    ================================================== -->

    <div class="row g-3 mb-4">


        <!-- TOTAL -->

        <div class="col-md-4">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#e9ecef;
                        color:#343a40;
                    "
                >

                    <i class="fa fa-users"></i>

                </div>


                <div class="stat-number">

                    <?= $totalUsers ?>

                </div>


                <div class="stat-label">

                    Total User

                </div>

            </div>

        </div>


        <!-- ACTIVE -->

        <div class="col-md-4">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#d1e7dd;
                        color:#0f5132;
                    "
                >

                    <i class="fa fa-user-check"></i>

                </div>


                <div class="stat-number">

                    <?= $activeUsers ?>

                </div>


                <div class="stat-label">

                    User Aktif

                </div>

            </div>

        </div>


        <!-- SUSPENDED -->

        <div class="col-md-4">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#f8d7da;
                        color:#842029;
                    "
                >

                    <i class="fa fa-user-slash"></i>

                </div>


                <div class="stat-number">

                    <?= $suspendedUsers ?>

                </div>


                <div class="stat-label">

                    Suspended

                </div>

            </div>

        </div>


    </div>


    <!-- =================================================
         USER LIST
    ================================================== -->

    <div class="user-card">


        <div class="p-3 border-bottom">

            <h5 class="mb-1">

                <i class="fa fa-users"></i>

                Daftar User

            </h5>


            <small class="text-muted">

                User yang tidak login selama 3 bulan
                akan otomatis disuspend.

            </small>

        </div>


        <div class="table-responsive">


            <table class="table table-hover">


                <thead class="table-light">

                    <tr>

                        <th>No.</th>

                        <th>Username</th>

                        <th>Role</th>

                        <th>Last Login</th>

                        <th>Tidak Aktif</th>

                        <th>Status</th>

                        <th>Penyimpanan</th>

                        <th>Tindakan</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($users)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="text-center py-4"
                        >

                            Belum ada user.

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $users
                    as $index => $user
                ): ?>


                    <tr>


                        <!-- NO -->

                        <td>

                            <?= $index + 1 ?>

                        </td>


                        <!-- USERNAME -->

                        <td>

                            <div class="username">

                                <?= htmlspecialchars(
                                    $user['username']
                                ) ?>

                            </div>

                        </td>


                        <!-- ROLE -->

                        <td>

                            <?php if (
                                $user['role'] === 'admin'
                            ): ?>

                                <span
                                    class="role-admin"
                                >

                                    ADMIN

                                </span>

                            <?php else: ?>

                                <span
                                    class="text-muted"
                                >

                                    USER

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- LAST LOGIN -->

                        <td>

                            <?php if (
                                !empty(
                                    $user['last_login']
                                )
                            ): ?>

                                <?= formatDateAdmin(
                                    $user['last_login']
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    Belum pernah login

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- INACTIVITY -->

                        <td>

                            <?php

                            if (
                                $user['role'] === 'admin'
                            ) {

                                echo '-';

                            }

                            else {

                                echo htmlspecialchars(
                                    inactivityText(
                                        $user['last_login']
                                    )
                                );

                            }

                            ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?php if (
                                $user['status'] === 'active'
                            ): ?>

                                <span
                                    class="status-active"
                                >

                                    <i
                                        class="fa fa-check-circle"
                                    ></i>

                                    ACTIVE

                                </span>

                            <?php else: ?>

                                <span
                                    class="status-suspended"
                                >

                                    <i
                                        class="fa fa-ban"
                                    ></i>

                                    SUSPENDED

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- STORAGE -->

                        <td>

                            <?= formatStorageAdmin(
                                $user['storage_used']
                            ) ?>

                            <span class="text-muted">

                                /

                                <?= formatStorageAdmin(
                                    $user['storage_quota']
                                ) ?>

                            </span>

                        </td>


                        <!-- ACTION -->

                        <td>


                            <?php if (
                                $user['role'] === 'admin'
                            ): ?>

                                <span
                                    class="text-muted"
                                >

                                    <i
                                        class="fa fa-shield-halved"
                                    ></i>

                                    Administrator

                                </span>


                            <?php elseif (
                                $user['status'] === 'active'
                            ): ?>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirm(
                                'Reset password user <?= htmlspecialchars($user['username'],ENT_QUOTES) ?> ke password default 123456 ?'
                                );">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reset">

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)$user['id'] ?>">

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-warning">

                                        <i class="fa fa-key"></i>

                                        Reset

                                    </button>

                                </form>

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Suspend user <?= htmlspecialchars(
                                                $user['username'],
                                                ENT_QUOTES
                                            ) ?>?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="suspend"
                                    >


                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)$user['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                    >

                                        <i
                                            class="fa fa-user-slash"
                                        ></i>

                                        Suspend

                                    </button>

                                </form>


                            <?php else: ?>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Aktifkan kembali user <?= htmlspecialchars(
                                                $user['username'],
                                                ENT_QUOTES
                                            ) ?>?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="activate"
                                    >


                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)$user['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-success"
                                    >

                                        <i
                                            class="fa fa-user-check"
                                        ></i>

                                        Aktifkan

                                    </button>

                                </form>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>

        </div>

    </div>


    <!-- =================================================
         FOOTER
    ================================================== -->

    <div class="text-center text-muted mt-4">

        <small>

            GosDrive Admin Panel

            &copy;

            <?= date('Y') ?>

        </small>

    </div>


</div>


</body>

</html>