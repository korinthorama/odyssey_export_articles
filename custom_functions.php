<?php

function detect_cms() {
    if (is_dir("../components")) return "joomla";
    if (is_dir("../wp-content")) return "wordpress";
    return false;
}

function export($cms) {
    global $messages, $db, $db_prefix, $zip_folder, $zipFile, $loading_file;
    emptyDirectory($zip_folder); // clean up before exporting new content
    @unlink($loading_file);
    $delimiter = ',';
    $enclosure = '"';
    $csv = $header = $filesToZip = array();
    $simple_fields = array('title', 'introtext', 'fulltext', 'created', 'state', 'featured', 'access');
    $header['title'] = "Τίτλος";
    $header['introtext'] = "Συνοπτική περιγραφή";
    $header['fulltext'] = "Περιεχόμενο του άρθρου";
    $header['category'] = "Κατηγορία δημοσίευσης";
    $header['created'] = "Ημερομηνία δημοσίευσης";
    $header['state'] = "Δημοσιευμένο";
    $header['featured'] = "Με χαρακτηριστική προβολή";
    $header['access'] = "Επίπεδο πρόσβασης";
    $header['images'] = "Εικόνες";
    $csv[] = $header;
    switch ($cms) {
        case "joomla":
            $categories = $images = array();
            // get categories
            $table = $db_prefix . 'categories';
            $records = $db->getRecords('select * from `' . $table . '` where `extension`="com_content"');
            if (!$records) {
                $messages->addError("No Joomla article categories found!");
                return false;
            }
            foreach ($records as $key => $record) {
                $category['title'] = $record->title;
                $category['description'] = $record->description;
                $params = json_decode($record->params);
                $category_image = $params->image;
                $category['image'] = $category_image;
                $categories[$record->id] = $category;
            }
            // get articles
            $table = $db_prefix . 'content';
            $records = $db->getRecords('select * from `' . $table . '`');
            if (!$records) {
                $messages->addError("No Joomla articles found!");
                return false;
            }
            $counter = 0;
            $articles_count = count($records);
            foreach ($records as $key => $record) {
                $line = $images = array();
                $introtext = $record->introtext;
                $fulltext = $record->fulltext;
                $urls = json_decode($record->urls);
                $article_content = get_article_content($cms, $introtext, $fulltext, $urls);
                $external_images = json_decode($record->images);
                $data = extract_data($cms, $article_content['body'], $external_images);
                $record->introtext = $article_content['minitext'];
                $record->fulltext = html_entity_decode($data['html'], ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
                $record->created = getTimestamp($record->created);
                $record->access = ($record->access <> 6) ? 1 : 0;
                $catID = $record->catid;
                $images = $data['images'];
                foreach ($header as $key => $val) {
                    if (in_array($key, $simple_fields)) {
                        $line[] = trim($record->$key);
                    }
                    if ($key == "category") {
                        $source = "../" . $categories[$catID]['image'];
                        if (is_file($source)) {
                            $images[] = array(
                                'src' => $categories[$catID]['image'],
                                'type' => 'category_image',
                                'default' => '0',
                                'title' => trim($categories[$catID]['title']),
                                'description' => trim($categories[$catID]['description'])
                            );
                        }
                        $line[] = trim($categories[$catID]['title']);
                    }
                }
                global $export_type;
                if ($export_type == "full") { // text & images must be exported
                    foreach ($images as $item) {
                        $source = "../" . $item['src'];
                        if (is_file($source)) {
                            $destination = $zip_folder . basename($item['src']);
                            copy($source, $destination);
                            $filesToZip[] = $destination;
                        }
                    }
                }
                $line[] = json_encode($images);
                $csv[] = $line;
                $counter++;
                manage_loading($articles_count, $counter);
            }
            $csvFile = $zip_folder . 'articles.csv';
            $fp = fopen($csvFile, 'w');
            foreach ($csv as $fields) {
                fputcsv($fp, $fields, $delimiter, $enclosure);
                $counter++;
            }
            fclose($fp);
            $filesToZip[] = $csvFile;
            $zip_results = create_zip($filesToZip, $zipFile, false);
            if ($zip_results !== true) {
                $zip_error = "Zip Error: " . getZipError($zip_results);
                $messages->addError($zip_error, true);
            }
            emptyDirectory($zip_folder, true); // delete all files except the exported zip
            @unlink($loading_file); // reset loading info
            return true;
            break;

        case "wordpress":
            // future use
            break;
    }
}

function manage_loading($total, $counter) {
    global $loading_file;
    $loading_file = (preg_match('/^([-\.\w]+)$/', $loading_file) > 0) ? $loading_file : "loading.txt"; // safe filename
    $loaded = file($loading_file);
    $loaded = (int)$loaded[0];
    $percent = (int)(100 * ($counter / $total));
    if ($percent > $loaded) {
        $fp = fopen($loading_file, "w");
        fwrite($fp, $percent);
        fclose($fp);
    }
}

function get_article_content($cms, $introtext, $fulltext, $urls = array()) {
    $article_content = array("minitext" => "", "body" => "");
    switch ($cms) {
        case "joomla":
            $links = array();
            $article_content['minitext'] = limit_text(strip_tags($introtext));
            $article_content['body'] = (empty($fulltext)) ? $introtext : $fulltext;
            if ($urls->urla) {
                $target = ($urls->targeta == "1") ? ' target="_blank" ' : '';
                $link = '<a href="' . $urls->urla . '"' . $target . '>' . $urls->urlatext . '</a>';
                $links[] = $link;
            }
            if ($urls->urlb) {
                $target = ($urls->targetb == "1") ? ' target="_blank" ' : '';
                $link = '<a href="' . $urls->urlb . '"' . $target . '>' . $urls->urlbtext . '</a>';
                $links[] = $link;
            }
            if ($urls->urlc) {
                $target = ($urls->targetc == "1") ? ' target="_blank" ' : '';
                $link = '<a href="' . $urls->urlc . '"' . $target . '>' . $urls->urlctext . '</a>';
                $links[] = $link;
            }
            $article_content['body'] = implode("<br>", $links) . $article_content['body'];
            break;
        case "wordpress":
            // future use
            break;
    }
    return $article_content;
}

function extract_data($cms, $html, $external_images) {
    switch ($cms) {
        case "joomla":
            $images = $meta = array();
            $dom = new DOMDocument();
            $dom->encoding = 'UTF-8';
            libxml_use_internal_errors(true);
            // load HTML with hack
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            foreach ($dom->childNodes as $item) {
                if ($item->nodeType == XML_PI_NODE) {
                    $dom->removeChild($item);// remove hack
                }
            }
            $tags = $dom->getElementsByTagName('hr');
            foreach ($tags as $key => $tag) {
                $node = $tags->item($key);
                if ($node->getAttribute('class') == 'system-pagebreak') $node->parentNode->removeChild($node); // remove joomla page breaks
            }
            $tags = $dom->getElementsByTagName('p');
            foreach ($tags as $key => $tag) {
                $node = $tags->item($key);
                if (preg_match('/^\{loadposition/', $node->nodeValue)) $node->parentNode->removeChild($node); // remove joomla modules from content
            }
            $tags = $dom->getElementsByTagName('a');
            foreach ($tags as $key => $tag) {
                $node = $tags->item($key);
                $href = $node->getAttribute('href');
                if (!preg_match('/^http/', $href)) { // its a relative link
                    if (preg_match('/^index.php\?Itemid=/', $href)) { //  its a menu link, a category link must be set
                        parse_str($href, $href_data);
                        $menu_id = $href_data["index_php?Itemid"]; // get category id from menu_id
                        $category_id = get_category_id('joomla', $menu_id);
                        $category_name = get_category_name('joomla', $category_id);
                        if ($category_name) { // if a valid category name has been extracted
                            $href = "http://odyssey.cms?name=" . base64_encode($category_name);
                            $node->setAttribute("href", $href);
                        }
                    }
                    if (preg_match('/^index.php\?option=com_content&view=article&id=/', $href)) { // set article link
                        parse_str($href, $href_data);
                        $article_id = $href_data["id"];
                        $article_name = get_article_name('joomla', $article_id);
                        $href = "http://odyssey.cms?name=" . base64_encode($article_name);
                        $node->setAttribute("href", $href);
                    }
                }
            }
            $tags = $dom->getElementsByTagName('img');
            foreach ($tags as $key => $tag) {
                $node = $tags->item($key);
                $src = $node->getAttribute('src');
                if (!preg_match('/^http/', $src)) { // its a local image
                    $img_id = uniqid();
                    $images[] = array(
                        'src' => $src,
                        'type' => 'body_image',
                        'default' => '0',
                        'title' => $node->getAttribute('title'),
                        'description' => $node->getAttribute('alt'),
                        'img_id' => $img_id
                    );
                    $node->setAttribute("src", "http://odyssey.cms?image=" . $img_id);
                }
            }
            global $export_type, $default_image_type;
            if ($export_type == "full") { // text & images must be exported
                $default_image = ($default_image_type == "image_intro" && $external_images->image_intro) ?
                    $external_images->image_intro : $external_images->image_fulltext;
                $default_image_title = ($default_image_type == "image_intro" && $external_images->image_intro) ?
                    $external_images->image_intro_caption : $external_images->image_fulltext_caption;
                $default_image_description = ($default_image_type == "image_intro" && $external_images->image_intro) ?
                    $external_images->image_intro_caption : $external_images->image_fulltext_caption;
                $secondary_image = ($default_image_type == "image_intro" && $external_images->image_intro) ?
                    $external_images->image_fulltext : $external_images->image_intro;
                $secondary_image_title = ($default_image_type == "image_intro" && $external_images->image_intro) ?
                    $external_images->image_fulltext_caption : $external_images->image_intro_caption;
                $secondary_image_description = ($default_image_type == "image_intro" && $external_images->image_intro) ?
                    $external_images->image_fulltext_caption : $external_images->image_intro_caption;
                if ($default_image) {
                    $images[] = array('src' => $default_image, 'type' => 'mediabank_image', 'default' => '1', 'title' => $default_image_title, 'description' => $default_image_description);
                }
                if ($secondary_image) {
                    $default_status = (empty($default_image)) ? '1' : '0';
                    $images[] = array('src' => $secondary_image, 'type' => 'mediabank_image', 'default' => $default_status, 'title' => $secondary_image_title, 'description' => $secondary_image_description);
                }
            }
            $html = str_replace(array(
                '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">',
                '<html><body>',
                '</body></html>'
            ), "", $dom->saveHTML());
            break;

        case "wordpress":
            // future use
            break;
    }
    return array("html" => $html, "images" => $images, "meta" => $meta);
}

function get_category_id($cms, $menu_id) {
    $category_id = false;
    switch ($cms) {
        case "joomla":
            global $db, $db_prefix;
            $table = $db_prefix . "menu";
            $q = "select `link` from `$table` where `id`='$menu_id'";
            $link = $db->getRecord($q)->link;
            parse_str($link, $link_data);
            $category_id = $link_data["id"];
            break;

        case "wordpress":
            // future use
            break;
    }
    return $category_id;
}

function get_article_name($cms, $id) {
    $article_name = false;
    switch ($cms) {
        case "joomla":
            global $db, $db_prefix;
            $table = $db_prefix . "content";
            $q = "select `title` from `$table` where `id`='$id'";
            $article_name = $db->getRecord($q)->title;
            break;

        case "wordpress":
            // future use
            break;
    }
    return $article_name;
}

function get_category_name($cms, $id) {
    $category_name = false;
    switch ($cms) {
        case "joomla":
            global $db, $db_prefix;
            $table = $db_prefix . "categories";
            $q = "select `title` from `$table` where `id`='$id'";
            $category_name = $db->getRecord($q)->title;
            break;

        case "wordpress":
            // future use
            break;
    }
    return $category_name;
}

function limit_text($text) {
    if (empty($max_chars)) return $text; // no limitation
    $len = strlen($text);
    $text = unicode_substr($text, 0, $max_chars);
    if (strlen($text) < $len) $text = trim($text) . "...";
    return $text;
}

function emptyDirectory($dir, $exclude_zip) {
    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($exclude_zip && pathinfo($file, PATHINFO_EXTENSION) == "zip") continue; // skip zip file
            unlink($file);
        }
    }
}

function getTimestamp($date) {
    $thedate = DateTime::createFromFormat("Y-m-d G:i:s", $date, new DateTimeZone("Europe/Athens"));
    return date_format($thedate, 'U');
}

function create_zip($files = array(), $destination = '', $path = true) {
    $valid_files = array();
    if (is_array($files)) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                $valid_files[] = $file;
            }
        }
    }
    if (count($valid_files)) {
        $zip = new ZipArchive();
        $zip_results = $zip->open($destination, ZIPARCHIVE::CREATE);
        if ($zip_results !== true) {
            return $zip_results; // return error code
        }
        foreach ($valid_files as $file) {
            $new_filename = (!$path) ? substr($file, strrpos($file, '/') + 1) : $file; // strip path or keep it
            $zip->addFile($file, $new_filename);
        }
        $zip->close();
        return file_exists($destination);
    } else {
        return false;
    }
}

function getZipError($code) {
    switch ($code) {
        case 0:
            return 'No error';
        case 1:
            return 'Multi-disk zip archives not supported';
        case 2:
            return 'Renaming temporary file failed';
        case 3:
            return 'Closing zip archive failed';
        case 4:
            return 'Seek error';
        case 5:
            return 'Read error';
        case 6:
            return 'Write error';
        case 7:
            return 'CRC error';
        case 8:
            return 'Containing zip archive was closed';
        case 9:
            return 'No such file';
        case 10:
            return 'File already exists';
        case 11:
            return 'Can`t open file';
        case 12:
            return 'Failure to create temporary file';
        case 13:
            return 'Zlib error';
        case 14:
            return 'Malloc failure';
        case 15:
            return 'Entry has been changed';
        case 16:
            return 'Compression method not supported';
        case 17:
            return 'Premature EOF';
        case 18:
            return 'Invalid argument';
        case 19:
            return 'Not a zip archive';
        case 20:
            return 'Internal error';
        case 21:
            return 'Zip archive inconsistent';
        case 22:
            return 'Can`t remove file';
        case 23:
            return 'Entry has been deleted';
        default:
            return 'An unknown error has occurred (' . intval($code) . ')';
    }
}


