<?php
declare(strict_types = 1);

// require_once('/path/to/vendor/autoload.php');
// or:
require_once('src/MessageStorage.php');

$storage = new \Attogram\MessageStorage('example.sqlite');

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>attogram/message-storage Example</title>
<style>
    body { font-family:sans-serif; }
    dl { border:1px solid grey; }
    dt { background-color:lightgrey; font-size:90%; padding:2px; }
    dd { font-family:monospace; font-size:120%; padding:5px; white-space:pre; }
    .error { color: red; font-weight:bold; }
</style>
</head>
<body>
<p>
    <a href="https://github.com/attogram/message-storage">attogram/message-storage</a>
    - <a href="">example form</a>
</p>
<?php

if (!$storage->isAlive()) {
    ?>
    <p class="error">
        ❌ Message Storage is temporarily unavailable.
    </p>
    <pre><?php print_r($storage->getErrors()); ?></pre>
    </body>
    </html>
    <?php
    exit;
}

/** @var bool $posted */
$posted = !empty($_POST) ? true : false;

/** @var string $message */
$message = !empty($_POST['message']) ? $_POST['message'] : '';

/** @var bool $consent */
$consent = !empty($_POST['consent']) && $_POST['consent'] == 'on' ? true : false;

if ($posted && $message && $consent) {
    $stored = $storage->save($message, 'example');
    if ($stored) {
        ?>
        <p>
            ✅ You gave consent to
            having this website store your submitted information
            so we can process your message.
        </p>
        <p>
            ✅ We stored <?php print count($stored); ?> items
            about your submission:
        </p>
        <dl>
        <?php
            foreach ($stored as $name => $value) {
                print '<dt>' . $name . '</dt>'
                    . '<dd>' . htmlentities($value) . '</dd>';
            }
        ?>
        </dl>
        </body>
        </html>
        <?php
        exit;
    }
    ?>
    <p class="error">
        ❌ Unable to save message
    </p>
    <pre class="error"><?php print_r($storage->getErrors()); ?></pre>
    <?php
}
?>
<form action="" method="POST">

    <?php if ($posted && empty($message)) { ?>
        <p class="error">
            ❌ Please enter a message:
        </p>
    <?php } else { ?>
        <p>
            Your message:
        </p>
    <?php } ?>
    <p><textarea name="message" id="message" cols="70" rows="7"><?php print $message; ?></textarea></p>
    <?php if ($posted && !$consent) { ?>
        <p class="error">
            ❌ You must consent to
            having this website store your submitted information
            so we can process your message.
        </p>
    <?php } ?>
    <p>
        <input type="checkbox" name="consent" />
        I consent to
        having this website store my submitted information
        so they can process my message.
    </p>
    <p>
        <input type="submit" value="           Send Message           " />
    </p>
</form>
</body>
</html>
