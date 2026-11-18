<?php
$storageFile = dirname(__DIR__) . '/storage/vulnerable-entries.json';
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
            header('Location: /vulnerable.php', true, 303);
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
    <h1>Vulnerable Guestbook — Local XSS Demo</h1>

    <form method="post">
        <label for="url">YouTube URL:</label>
        <input id="url" name="url" type="text" required>
        <button type="submit">Add video</button>
    </form>

    <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>

    <h2>Saved entries</h2>
    <ul>
        <?php foreach (array_reverse($entries) as $entry): ?>
            <li class="video"
                data-url="<?php echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <script>
    // Deliberately vulnerable rendering for the local XSS lab.
    document.querySelectorAll('.video').forEach(function (video) {
        const url = video.getAttribute('data-url');
        const videoId = url.split('v=')[1]?.split('&')[0] ?? '';

        video.innerHTML =
            '<a href="' + url + '">' +
            '<img src="https://i.ytimg.com/vi/' + videoId +
            '/hqdefault.jpg" alt="Video thumbnail">' +
            '</a>';
    });
    </script>
</body>
</html>
