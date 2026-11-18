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
    } elseif (!preg_match(
        '~\Ahttps://(?:www\.)?youtube\.com/watch\?v=[A-Za-z0-9_-]{11}\z~',
        $url
    )) {
        $message = 'Enter a YouTube URL in the format https://www.youtube.com/watch?v=VIDEO_ID';
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
        <?php foreach (array_reverse($entries) as $entry): ?>
            <li class="video"
                data-url="<?php echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?>
            </li>
        <?php endforeach; ?>
    </ul>
<script>
    document.querySelectorAll('.video').forEach(function (video) {
        const rawUrl = video.getAttribute('data-url');

        try {
            const url = new URL(rawUrl);
            const allowedHosts = ['www.youtube.com', 'youtube.com'];
            const videoId = url.searchParams.get('v');

            if (
                url.protocol !== 'https:' ||
                !allowedHosts.includes(url.hostname) ||
                url.port !== '' ||
                url.username !== '' ||
                url.password !== '' ||
                url.pathname !== '/watch' ||
                !/^[A-Za-z0-9_-]{11}$/.test(videoId ?? '')
            ) {
                throw new Error('Invalid YouTube URL');
            }

            const link = document.createElement('a');
            link.href = 'https://www.youtube.com/watch?v=' + videoId;

            const image = document.createElement('img');
            image.src = 'https://i.ytimg.com/vi/' + videoId + '/hqdefault.jpg';
            image.alt = 'Video thumbnail';

            link.appendChild(image);
            video.replaceChildren(link);
        } catch {
            video.textContent = 'Invalid YouTube URL — entry not rendered.';
        }
    });
</script>
</body>
</html>
