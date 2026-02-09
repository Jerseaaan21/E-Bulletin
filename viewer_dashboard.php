<?php
session_start();
// Allow access to both logged-in users and guests
if (!isset($_SESSION['user_info']) && !isset($_SESSION['guest'])) {
    header('Location: login.php');
    exit();
}

// Handle guest access
if (!isset($_SESSION['user_info']) && !isset($_SESSION['guest'])) {
    $_SESSION['guest'] = true;
}

// Include database connection
include "db.php";

// Get departments from database
$departments_query = "SELECT dept_id, dept_name, acronym FROM departments ORDER BY dept_name";
$departments_result = $conn->query($departments_query);
$departments = [];

if ($departments_result && $departments_result->num_rows > 0) {
    while ($row = $departments_result->fetch_assoc()) {
        // Map icons based on department acronym
        $icon = 'fa-university'; // Default icon
        switch (strtolower($row['acronym'])) {
            case 'ceit':
                $icon = 'fa-university';
                break;
            case 'dit':
                $icon = 'fa-laptop-code';
                break;
            case 'dafe':
                $icon = 'fa-seedling';
                break;
            case 'dcea':
                $icon = 'fa-building';
                break;
            case 'dceee':
                $icon = 'fa-microchip';
                break;
            case 'diet':
                $icon = 'fa-industry';
                break;
        }

        $departments[$row['dept_id']] = [
            'name' => $row['dept_name'],
            'file' => 'bulletin.php?dept_id=' . $row['dept_id'],
            'icon' => $icon,
            'acronym' => $row['acronym']
        ];
    }
}

// Get user info if logged in
$user_name = isset($_SESSION['user_info']) ? $_SESSION['user_info']['name'] : 'Guest';
$user_role = isset($_SESSION['user_info']) ? $_SESSION['user_info']['role'] : 'Guest';
$is_guest = isset($_SESSION['guest']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEIT Bulletin Viewer</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #e7621f, #160700);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(5px);
        }

        .header h1 {
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info span {
            margin-right: 15px;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            backdrop-filter: blur(5px);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 2px solid transparent;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-icon {
            font-size: 24px;
            margin-right: 15px;
            color: #ffd700;
        }

        .card h3 {
            font-size: 20px;
            font-weight: 500;
        }

        .card p {
            margin-bottom: 20px;
            opacity: 0.8;
            flex-grow: 1;
        }

        .view-btn {
            background: #fff;
            color: #e7621f;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
            align-self: flex-start;
        }

        .view-btn:hover {
            background: #f8f8f8;
        }

        .guest-banner {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .guest-banner p {
            margin: 0;
        }

        .login-link {
            background: #fff;
            color: #e7621f;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }

        .login-link:hover {
            background: #f8f8f8;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .user-info {
                margin-top: 15px;
            }

            .cards-container {
                grid-template-columns: 1fr;
            }

            .guest-banner {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>CEIT E-Bulletin Viewer</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
                <?php if ($is_guest): ?>
                    <a href="login.php" class="logout-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php else: ?>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_guest): ?>
            <div class="guest-banner">
                <p>You are currently viewing as a guest. You can view all department bulletins but cannot make changes.</p>
                <a href="login.php" class="login-link">Login as Staff</a>
            </div>
        <?php endif; ?>

        <div class="cards-container">
            <?php foreach ($departments as $id => $dept): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas <?php echo $dept['icon']; ?> card-icon"></i>
                        <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                    </div>
                    <p>View the latest bulletin and announcements for this department.</p>
                    <a href="<?php echo htmlspecialchars($dept['file']); ?>" class="view-btn">
                        <i class="fas fa-eye"></i> View Department
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>