<?php
session_start();
include "config.php";

// Periksa apakah pengguna telah login
if (!isset($_SESSION['user_name'])) {
    header('Location: user_page.php');
    exit;
  }

// Variabel default
$profile_image = 'default.jpg';
$streak_count = 0;
$total_comments = 0;
$badges = []; // Array untuk menyimpan badges yang sudah diperoleh

if (isset($_SESSION['user_name'])) {
    $user_name = $_SESSION['user_name'];
    $current_date = date("Y-m-d");

    // *1. Query untuk mendapatkan gambar profil*
    $image_query = "SELECT image FROM user_form WHERE name = '$user_name'";
    $image_result = mysqli_query($conn, $image_query);
        /*i walk a frozen lake*/
    if ($image_result && mysqli_num_rows($image_result) > 0) {
        $row = mysqli_fetch_assoc($image_result);
        $image_path = $row['image'] ? 'uploaded_profile_images/' . $row['image'] : 'default.jpg';
        $profile_image = $image_path . '?t=' . time(); // Tambahkan parameter unik untuk memaksa refresh
    }

    // *2. Query untuk mendapatkan last_login, streak_count, dan badges*
    $streak_query = "SELECT last_login, streak_count, badges FROM user_form WHERE name = '$user_name'";
    $streak_result = mysqli_query($conn, $streak_query);

    if ($streak_result && mysqli_num_rows($streak_result) > 0) {
        $user_data = mysqli_fetch_assoc($streak_result);

        $last_login = $user_data['last_login'];
        $streak_count = $user_data['streak_count'];
        $badges = $user_data['badges'] ? explode(',', $user_data['badges']) : []; // Ambil badges sebagai array

        // Cek apakah pengguna login hari ini atau melewatkan hari
        $last_login_date = date('Y-m-d', strtotime($last_login));
        if ($last_login_date != $current_date) {
            if (date('Y-m-d', strtotime($last_login . ' +1 day')) == $current_date) {
                $streak_count++; // Tambahkan streak
            } else {
                $streak_count = 1; // Reset streak
            }

            // Update last_login dan streak_count
            $update_query = "UPDATE user_form SET last_login = '$current_date', streak_count = '$streak_count' WHERE name = '$user_name'";
            mysqli_query($conn, $update_query);
        }
    }

    // *3. Query untuk mendapatkan total review*
    $comment_query = "SELECT COUNT(*) AS total_comments FROM comments WHERE username = '$user_name'";
    $comment_result = mysqli_query($conn, $comment_query);
    if ($comment_result && mysqli_num_rows($comment_result) > 0) {
        $comment_data = mysqli_fetch_assoc($comment_result);
        $total_comments = intval($comment_data['total_comments']);
    }

    // *5. Logika pemberian badge*
    $updated_badges = [];

    // Badge 1: streak > 10
    if ($streak_count >= 10) {
        $updated_badges[] = 'badge01';
    }

    // Badge 3: 15 review
    if ($total_comments >= 15) {
        $updated_badges[] = 'badge3';
    }

    // *6. Simpan data badges kembali ke database*
    $badges_string = implode(',', $updated_badges); // Gabungkan badge menjadi string
    $update_badges_query = "UPDATE user_form SET badges = '$badges_string' WHERE name = '$user_name'";
    if (!mysqli_query($conn, $update_badges_query)) {
        die('Update badges query failed: ' . mysqli_error($conn));
    }

    // Perbarui variabel $badges
    $badges = $updated_badges;

    // Query to get the books with the most comments by the user
$top_read_query = "
SELECT book_title, COUNT(*) AS comment_count
FROM comments
WHERE username = '$user_name'
GROUP BY book_title
ORDER BY comment_count DESC
LIMIT 4"; // Limit to 4 top books
$top_read_result = mysqli_query($conn, $top_read_query);    

}

function getBookImageFromAPI($book_title) {
    // Correct the API URL to include the search query parameter
    $api_url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($book_title);

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    // Check if the response is valid
    if ($response) {
        $data = json_decode($response, true);
        
        // Check if there are any results in the 'items' field
        if (isset($data['items']) && count($data['items']) > 0) {
            // Extract the image URL from the first book result
            $book_image_url = $data['items'][0]['volumeInfo']['imageLinks']['thumbnail'] ?? '';
            
            if ($book_image_url) {
                return $book_image_url;
            }
        }
    }

    // Return a default image if the API fails or no image found
    return "assets/default-book-image.jpg";

    // Ambil username dari sesi
    $user_name = $_SESSION['user_name'];

    // Query untuk menghitung jumlah shelves yang ditambahkan oleh pengguna
    $query_shelves_count = "SELECT COUNT(*) AS total_shelves FROM shelves WHERE username = '$user_name'";
    $result_shelves_count = mysqli_query($conn, $query_shelves_count);

    $total_shelves = 0; // Default nilai shelves jika query gagal
    if ($result_shelves_count) {
        $row_shelves_count = mysqli_fetch_assoc($result_shelves_count);
        $total_shelves = $row_shelves_count['total_shelves'];
    }
}


?>

<!DOCTYPE html>
<html lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookBerry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet"> <!-- Link Poppins -->
    <link href="style/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@latest/css/boxicons.min.css">
    <style>
body {
    justify-content: center;
}

/* Container styles */
.container {
    width: 100%;
    padding: 30px;
    border-radius: 10px;
}

/* Profile button styles */
.profile-btn {
    display: inline-block;
    padding: 5px 10px;
    background-color: #1e2a5e;
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: medium;
    text-align: center;
    font-size: small;
}

.profile-btn:hover {
    background-color: #fff;
    color: #1e2a5e;
    border: 3px solid #1e2a5e;
}

/* Badge container styles */
.badge-container {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}

/* Badge item styles */
.badge-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.badge-item img {
    width: 100px;
    height: 100px;
    object-fit: contain;
}

.badge-item p {
    margin-top: 8px;
    font-size: 14px;
    color: #333;
}

/* Badge title and extra info */
.badge-title {
    font-size: 16px;
    font-weight: bold;
    margin-top: 10px;
    margin-bottom: 2px;
}

.badge-extra {
    font-size: 14px;
    color: #555;
    margin-top: 0px;
    margin-bottom: 0px;
}

/* Profile header styles */
.profile-header {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 30px;
    margin-bottom: 30px;
}

/* Profile info section */
.profile-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.profile-info img {
    border-radius: 50%;
    width: 150px;
    height: 150px;
}

/* Stats container styles */
.stats {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    flex-wrap: wrap;
}

/* Stat item styles */
.stat-item {
    background-color: #f5f1d5;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    flex-basis: 22%;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-item h3 {
    font-size: 32px;
    font-weight: bold;
}

.stat-item p {
    font-size: 16px;
    color: #6e6e6e;
    font-weight: normal;
}

/* Section title styles */
.section-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
}

/* Bookshelf and shelf container styles */
.shelves-container {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

/* Book item and shelf styles */
.book-item,
.shelf {
    text-align: center;
    flex-basis: 22%;
}

.book-item img,
.shelf img {
    width: 100%;
    max-width: 150px;
    height: auto;
    object-fit: cover;
}

.shelf-stats {
    font-size: 12px;
    color: #ded3d3;
}

/* Media Queries for Smaller Screens */

/* For medium screens (tablets) */
@media (max-width: 1200px) {
    .stat-item {
        flex-basis: 45%;
    }
}

/* Add Shelf Styles */
.add-shelf {
    margin-bottom: 30px;
    background-color: #f5f5f5;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.add-shelf form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.add-shelf input,
.add-shelf textarea {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

/* Top Read Styles */
.top-read {
    padding: 20px;
    margin-top: 30px;
}

.bookshelf {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
}

.bookshelf-item {
    flex-basis: 22%;
    margin-bottom: 20px;
    background-color: #fff;
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.bookshelf-item img {
    width: 100%;
    height: auto;
    border-radius: 8px;
}

/* Bookshelf responsiveness for smaller screens */
/* For small screens (phones) */
@media (max-width: 768px) {
    .section-title {
        font-size: 24px;
    }

    .bookshelf {
        justify-content: center;
    }

    .bookshelf-item {
        flex-basis: 45%;
    }
    .badge-container {
        flex-direction: column;
        align-items: center;
    }

    .stat-item {
        flex-basis: 100%;
    }

    .stats {
        flex-direction: column;
        align-items: center;
    }

    .section-title {
        font-size: 20px;
        text-align: center;
    }

    .profile-info {
        display: flex;
        align-items: center;
        gap: 20px;
        justify-content: flex-start;
    }

    .badge-item img {
        width: 80px;
        height: 80px;
    }

    .stat-item h3 {
        font-size: 20px;
    }

    .book-item,
    .shelf {
        flex-basis: 100%;
    }
}

/* For extra small screens (phones) */
@media (max-width: 400px) {
    .section-title {
        font-size: 20px;
        text-align: center;
    }

    .bookshelf {
        flex-direction: column;
        align-items: center;
    }

    .bookshelf-item {
        flex-basis: 80%;
        margin-bottom: 15px;
    }

    .profile-info {
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .profile-info img {
        width: 120px;
        height: 120px;
        margin-bottom: 10px;
    }

    .stats {
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .stat-item {
        flex-basis: 100%;
        margin-bottom: 10px;
        padding: 15px;
    }

    .stat-item h3 {
        font-size: 24px;
    }

    .stat-item p {
        font-size: 14px;
    }
}
</style>

<body>
    <?php include "layout/header.html" ?>

    <div class="container">
        <div class="profile-header">
            <div class="profile-info">
                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Picture" class="img-fluid">
                <div>
                    <div class="profile-name"><?php echo $_SESSION['user_name']; ?></div>
                    <a href="update_profile.php" class="profile-btn">Edit Profile</a>
                </div>
            </div>

            <div class="badge-container">
                <?php 
                $badge_descriptions = [
                    'badge01' => ['title' => 'Steady Reader', 'extra' => 'Has reached 5 Streaks'],
                    'badge3' => ['title' => 'Page Turner', 'extra' => 'Has reached 15 reviews']
                ];
                
                foreach ($badges as $badge) { ?>
                    <div class="badge-item">
                        <img src="assets/<?php echo htmlspecialchars($badge); ?>.png" alt="<?php echo htmlspecialchars($badge); ?>" class="img-fluid">
                        <p class="badge-title"><?php echo $badge_descriptions[$badge]['title'] ?? ''; ?></p>
                        <p class="badge-extra"><?php echo $badge_descriptions[$badge]['extra'] ?? ''; ?></p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="stats">
            <div class="stat-item">
                <h3><?php echo htmlspecialchars($streak_count); ?></h3>
                <p>Streak</p>
            </div>
            <div class="stat-item">
                <h3><?php echo htmlspecialchars($total_comments); ?></h3>
                <p>Reviews</p>
            </div>
            <div class="stat-item">
                <h3><?php echo htmlspecialchars(count($badges)); ?></h3>
                <p>Badges</p>
            </div>
            <div class="stat-item">
                <h3><?php echo htmlspecialchars($total_shelves); ?> </h3>
                <p>Shelves</p>
            </div>
        </div>

        <div class="top-read">
    <div class="section-title"><?php echo $_SESSION['user_name']; ?>'s Top Read</div>
    <div class="bookshelf">
        <?php
        if ($top_read_result && mysqli_num_rows($top_read_result) > 0) {
            while ($row = mysqli_fetch_assoc($top_read_result)) {
                $book_title = htmlspecialchars($row['book_title']);
                $comment_count = $row['comment_count'];

                // Get the book image URL from the API
                $book_image = getBookImageFromAPI($book_title); // Fetch image from API
                ?>
                <div class="book-item">
                    <img src="<?php echo $book_image; ?>" alt="Book Cover">
                    <p><?php echo $book_title; ?></p>
                    <p>Comments: <?php echo $comment_count; ?></p>
                </div>
                <?php
            }
        } else {
            echo "<p>No top read books found.</p>";
        }
        ?>
    </div>
</div>

<div class="section-title">Add a Book to Your Shelves</div>
<div class="add-shelf">
    <form action="add_shelf.php" method="POST" enctype="multipart/form-data">
        <input type="text" name="book_title" placeholder="Book Title" required>
        <textarea name="description" placeholder="Short Description"></textarea>
        <input type="file" name="book_image" accept="image/*">
        <button type="submit" class="info-btn">Add to Shelves</button>
    </form>
</div>

<div class="shelves">
    <div class="section-title"><?php echo $_SESSION['user_name']; ?>'s Shelves</div>
    <div class="shelves-container">
        <?php
        $shelf_query = "SELECT * FROM shelves WHERE username = '$user_name' ORDER BY created_at DESC";
        $shelf_result = mysqli_query($conn, $shelf_query);

        if ($shelf_result && mysqli_num_rows($shelf_result) > 0) {
            $book_count = mysqli_num_rows($shelf_result); // Count the number of books
            echo "<p>Total Books: $book_count</p>"; // Display the number of books
            while ($row = mysqli_fetch_assoc($shelf_result)) {
                $book_title = htmlspecialchars($row['book_title']);
                $description = htmlspecialchars($row['description']);
                $book_image = htmlspecialchars($row['book_image']);
                $book_image_path = !empty($book_image) ? 'uploaded_books/' . $book_image : 'assets/default-book-image.jpg';
                ?>
                <div class="shelf">
                    <img src="<?php echo $book_image_path; ?>" alt="<?php echo $book_title; ?>">
                    <h4><?php echo $book_title; ?></h4>
                    <p><?php echo $description; ?></p>
                </div>
                <?php
            }
        } else {
            echo "<p>You haven't added any books yet.</p>";
        }
        ?>
    </div>
</div>
        <a href="user_page.php" class="info-btn">Log Out</a>
    </div>

    <?php include "layout/footer.html" ?>

    <script src="https://code.jquery.com/jquery-3.2.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/script.js"></script>

</body>

</html>