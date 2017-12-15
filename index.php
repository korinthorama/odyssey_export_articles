<?php
require("dll.php");
set_time_limit(0); // no time out for this script
$cms = detect_cms();
if (!is_dir($zip_folder)) $messages->addError("Zip folder is missing!");
if (!is_writable($zip_folder)) $messages->addError("Zip folder is not writable!");
?>
<!DOCTYPE HTML>
<html lang="el">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Export content to Odyssey CMS</title>
    <meta name="Generator" content="Odyssey Framework (https://odyssey.webpage.gr)">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:400italic,400,700&subset=latin,greek' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans&subset=latin,greek' rel='stylesheet' type='text/css'>
    <link href="css/styles.css" rel="stylesheet" type="text/css">
    <link href="css/custom_styles.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="js/jquery.js"></script>
    <script type="text/javascript" src="js/app.js"></script>

</head>
<body>
<div id="container">
    <h1 class="centered">Export content to Odyssey CMS</h1>
    <?php
    switch ($cms) {
        case "joomla":
            $content_type = "articles";
            if ($_POST['action'] == 'Export') {
                $max_chars = (int)$_POST['max_chars'];
                $default_image_type = $_POST['default_image_type'];
                $include_image_intro = (int)$_POST['include_image_intro'];
                export($cms);
            }
            break;

        case "wordpress":
            $content_type = "posts";
            $messages->addError("WordPress detected but it is not implemented yet!");
            break;

        default:
            $messages->addError("No valid CMS detected! Are you sure the script's folder is placed in CMS's root directory?");
    }

    $hasErrors = $messages->hasErrors;
    $messages->printSystemMessages();
    if ($_POST['action'] != 'Export' && !$hasErrors) {
        ?>
        <p class="centered">Ready to export <?php echo $content_type; ?> from:<span class="cms"><?php echo ucfirst($cms); ?></span></p>
        <form action="index.php" method="post">
            <?php
            switch ($cms) {
                case "joomla":
                    ?>
                    <div class="form_element">
                        <div class="form_label">
                            Max characters for intro text :
                            <div class="form_sublabel">(Leave empty to keep entire intro text)</div>
                        </div>
                        <div class="normal_wrapper">
                            <input name="max_chars" id="max_chars" class="listbox numeric" type="text">
                        </div>
                    </div>
                    <div class="form_element">
                        <div class="form_label">
                            Image type to be used as default image in article :
                            <div class="form_sublabel">(Both images can be included in export)</div>
                        </div>
                        <div class="normal_wrapper">
                            <select class="listbox" name="default_image_type" id="default_image_type">
                                <option value="image_intro">Image Intro</option>
                                <option value="image_fulltext" selected="selected">Image Fulltext</option>
                            </select>
                        </div>
                    </div>
                    <div class="form_element">
                        <div class="checkbox_wrapper">
                            <input name="include_image_intro" id="include_image_intro" value="1" type="checkbox" checked="checked">
                            <p class="form_label">Include Image Intro in export</p>
                        </div>
                    </div>
                    <?php
                    break;
                case "wordpress":
                    // future use
                    break;
            }
            ?>
            <input type="submit" name="action" value="Export" class="btn btn_submit">
        </form>
        <?php
    }
    if ($_POST['action'] == 'Export') {
        if (!$hasErrors) {
            ?>
            <p class="centered">Export completed!</p>
            <p class="centered"><a class="btn" href="<?php echo $zipFile; ?>">Download</a> <a class="btn" href="index.php">Try Again</a></p>
            <?php
        } else {
            ?>
            <p class="centered"><a class="btn" href="index.php">Try Again</a></p>
            <?php
        }
    }
    ?>
</div>
</body>
</html>