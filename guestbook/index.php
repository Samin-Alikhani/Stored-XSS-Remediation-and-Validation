<?php
$storageFile = dirname(__DIR__) . '/storage/entries.json';
$message = '';
$entries = [];

if (is_file($storageFile)) {
    $entries = json_decode(
        file_get_contents($storageFile),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUrl = $_POST['url'] ?? '';
    $url = is_string($submittedUrl) ? trim($submittedUrl) : '';

    if ($url === '') {
        $message = 'Please enter a URL.';
    } else {
        $entries[] = $url;

        $saved = file_put_contents(
            $storageFile,
            json_encode($entries, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            LOCK_EX
        );

        if ($saved === false) {
            $message = 'Could not save the entry.';
            array_pop($entries);
        } else {
            header('Location: /', true, 303);
            exit;
        }
    }
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

    <h2>Saved entries</h2>
    <ul>
        <?php foreach ($entries as $entry): ?>
            <li><?php echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
