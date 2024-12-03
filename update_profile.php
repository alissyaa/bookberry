<?php
include 'config.php';
session_start();

$user_name = $_SESSION['user_name'];

if (!isset($user_name)) {
    header('location:user_page.php');
    exit();
}

$message = [];

// Create a connection to PostgreSQL
$conn = pg_connect("host=localhost dbname=bookberryss user=postgres password=kamisukses");

if (!$conn) {
    die('Connection failed: ' . pg_last_error());
}

// Fetch user data and profile image from the database
$query = pg_query($conn, "SELECT * FROM user_form WHERE name = '$user_name'");

if (pg_num_rows($query) > 0) {
    $user_data = pg_fetch_assoc($query);
    $profile_image = !empty($user_data['image']) ? 'uploaded_profile_images/' . $user_data['image'] : 'uploaded_profile_images/default_image.jpg';
} else {
    $message[] = 'User data not found.';
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    // Update name and email if needed
    $update_name = pg_escape_string($conn, $_POST['update_name']);
    $update_email = pg_escape_string($conn, $_POST['update_email']);
    pg_query($conn, "UPDATE user_form SET name = '$update_name', email = '$update_email' WHERE name = '$user_name'") or die('Update failed');

    // Check if a new profile image is uploaded
    if (isset($_FILES['update_image']) && $_FILES['update_image']['error'] == 0) {
        $image_name = $_FILES['update_image']['name'];
        $image_tmp_name = $_FILES['update_image']['tmp_name'];
        $image_folder = 'uploaded_profile_images/' . $image_name;

        // Move uploaded image to the specified folder and update in database
        if (move_uploaded_file($image_tmp_name, $image_folder)) {
            pg_query($conn, "UPDATE user_form SET image = '$image_name' WHERE name = '$user_name'") or die('Image update failed');
            $message[] = "Profile image updated successfully!";
            $profile_image = $image_folder;  // Update variable to show new image instantly
        } else {
            $message[] = "Failed to upload image.";
        }
    }
}

// Handle account deletion
if (isset($_POST['delete_account'])) {
    pg_query($conn, "DELETE FROM shelves WHERE username = '$user_name'") or die('Failed to delete from shelves');

    if (!empty($user_data['image']) && $user_data['image'] !== 'default_image.jpg') {
        unlink('uploaded_profile_images/' . $user_data['image']);
    }

    // Delete user data from database
    pg_query($conn, "DELETE FROM user_form WHERE name = '$user_name'") or die('Delete failed');
    session_destroy();
    header('location:user_page.php');
    exit();
}

pg_close($conn);  // Close the PostgreSQL connection
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookBerry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #1e2a5e;
            margin: 0;
            padding-top: 90px;
        }

        header {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #1e2a5e;
            z-index: 1000;
        }

        header .logo {
            color: #fff;
            font-size: 30px;
            text-decoration: none;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0 auto;
        }

        .logo-image {
            max-width: 170px;
            height: auto;
            display: block;
        }

        .navigation {
            display: flex;
        }

        .navigation a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            letter-spacing: 1px;
            padding: 2px 15px;
            border-radius: 20px;
            transition: 0.3s background;
            margin-right: 30px;
        }

        .logo img {
            max-height: 50px;
            width: auto;
            vertical-align: left;
            align-items: left;
            margin-right: auto;
        }

        .navigation a:hover {
            background: #fff;
            color: #1e2a5e;
        }

        input[type="text"],
        input[type="password"],
        input[type="file"] {
            color: black;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 50px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="file"]:focus {
            border-color: #1e2a5e;
            outline: none;
        }

        .toggle-password {
            position: absolute;
            top: 70%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #1E2A5E;
            font-size: 0.7rem;
        }
    </style>
    <script>
        function togglePasswordVisibility(id) {
            const passwordField = document.getElementById(id);
            const eyeIcon = document.getElementById(id + '-eye');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</head>

<body>
    <?php include "layout/header.html" ?>

    <div class="container mx-auto">
        <?php foreach ($message as $msg): ?>
            <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>

        <div class="bg-white p-8 shadow-md rounded-[20px]">
            <h2 class="text-2xl font-bold mb-4">Account Information</h2>
            <div class="text-center mb-6">
                <div class="flex justify-center items-center">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Image" class="rounded-full w-32 h-32 object-cover">
                </div>
                <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($user_data['name'] ?? 'No Name'); ?></h3>
                <p class="text-gray-600"><?php echo htmlspecialchars($user_data['email'] ?? 'No Email'); ?></p>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700" for="update_name">Name:</label>
                        <input type="text" id="update_name" name="update_name" class="w-full" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="block text-gray-700" for="update_email">Email:</label>
                        <input type="text" id="update_email" name="update_email" class="w-full" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="relative">
                    <label class="block text-gray-700" for="old_pass">Old Password:</label>
                    <input type="password" id="old_pass" name="old_pass" class="w-full" placeholder="Enter your old password">
                    <i class="fas fa-eye toggle-password" id="old_pass-eye" onclick="togglePasswordVisibility('old_pass')"></i>
                </div>
                <div class="relative">
                    <label for="new_pass" class="block text-gray-700">New Password:</label>
                    <input type="password" id="new_pass" name="new_pass" class="w-full" placeholder="Enter new password">
                    <i class="fas fa-eye toggle-password" id="new_pass-eye" onclick="togglePasswordVisibility('new_pass')"></i>
                </div>
                <div class="relative">
                    <label for="confirm_pass" class="block text-gray-700">Confirm New Password:</label>
                    <input type="password" id="confirm_pass" name="confirm_pass" class="w-full" placeholder="Confirm new password">
                    <i class="fas fa-eye toggle-password" id="confirm_pass-eye" onclick="togglePasswordVisibility('confirm_pass')"></i>
                </div>

                <div>
                    <label for="update_image" class="block text-gray-700">Profile Image:</label>
                    <input type="file" name="update_image" class="w-full" id="update_image">
                </div>

                <button type="submit" name="update_profile" class="w-full bg-blue-500 text-white py-2 rounded-md">Update Profile</button>
            </form>

            <form method="POST" class="mt-4">
                <button type="submit" name="delete_account" class="w-full bg-red-500 text-white py-2 rounded-md">Delete Account</button>
            </form>
        </div>
    </div>
</body>

</html>
