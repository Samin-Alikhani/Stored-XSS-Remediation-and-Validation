
<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    $message = 'Received URL: ' . $url;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Local Guestbook Lab</title>
</head>
<body>
    <h1>Local Guestbook Lab</h1>

    <form method="post">
        <label for="url">YouTube URL:</label>
        <input id="url" name="url" type="text" required>
        <button type="submit">Add video</button>
    </form>

    <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
</body>
</html>
