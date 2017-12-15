<?php

function detect_cms() {
    if (is_dir("../components")) return "joomla";
    if (is_dir("../wp-content")) return "wordpress";
    return false;
}

function export($cms) {
    global $messages, $db, $db_prefix, $zip_folder, $zipFile;
    emptyDirectory($zip_folder); // clean up before exporting new content
    $delimiter = ',';
    $enclosure = '"';
    $csv = $header = $filesToZip = array();
    $simple_fields = array('title', 'introtext', 'fulltext', 'created', 'state', 'featured', 'access');
    $header['title'] = "Title";
    $header['introtext'] = "Minitext";
    $header['fulltext'] = "Body";
    $header['category'] = "Category";
    $header['created'] = "Published Date";
    $header['state'] = "Active";
    $header['featured'] = "Featured";
    $header['access'] = "Access";
    $header['images'] = "Images";
    $csv[] = $header;
    switch ($cms) {
        case "joomla":
            $categories = $images = array();
            // categories
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
            // articles
            $table = $db_prefix . 'content';
            $records = $db->getRecords('select * from `' . $table . '`');
            if (!$records) {
                $messages->addError("No Joomla articles found!");
                return false;
            }
            foreach ($records as $key => $record) {
                $line = $images = array();
                $introtext = $record->introtext;
                $fulltext = $record->fulltext;
                $urls = json_decode($record->urls);
                $article_content = get_article_content($cms, $introtext, $fulltext, $urls);
                $external_images = json_decode($record->images);
                $data = extract_data($cms, $article_content['body'], $external_images);
                $record->introtext = $article_content['minitext'];
                $record->fulltext = $data['html'];
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
                foreach ($images as $item) {
                    $source = "../" . $item['src'];
                    if (is_file($source)) {
                        $destination = $zip_folder . basename($item['src']);
                        copy($source, $destination);
                        $filesToZip[] = $destination;
                    }
                }
                $line[] = json_encode($images);
            }
            $csv[] = $line;
            $csvFile = $zip_folder . 'articles.csv';
            $fp = fopen($csvFile, 'w');
            foreach ($csv as $fields) fputcsv($fp, $fields, $delimiter, $enclosure);
            fclose($fp);
            $filesToZip[] = $csvFile;
            $zip_results = create_zip($filesToZip, $zipFile, false);
            if($zip_results !== true){
                $zip_error = "Zip Error: " . getZipError($zip_results);
                $messages->addError($zip_error, true);
            }
            emptyDirectory($zip_folder, true); // delete all files except the exported zip
            return true;
            break;

        case "wordpress":
            // future use
            break;
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
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS);
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
                    if (preg_match('/^index.php\?Itemid=/', $href)) { //  set category link
                        parse_str($href, $href_data);
                        $cat_id = $href_data["index_php?Itemid"];
                        $href = "category=" . $cat_id;
                        $node->setAttribute("href", $href);
                    }
                    if (preg_match('/^index.php\?option=com_content&view=article&id=/', $href)) { // set article link
                        parse_str($href, $href_data);
                        $article_id = $href_data["id"];
                        $href = "article=" . $article_id;
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
                    $images[] = array('src' => $src, 'type' => 'body_image', 'default' => '0', 'title' => $node->getAttribute('title'), 'description' => $node->getAttribute('alt'));
                    $node->setAttribute("src", "image=" . $img_id);
                }
            }
            global $default_image_type, $include_image_intro;
            $default_image = ($default_image_type == "image_intro") ? $external_images->image_intro : $external_images->image_fulltext;
            $default_image_title = ($default_image_type == "image_intro") ? $external_images->image_intro_caption : $external_images->image_fulltext_caption;
            $default_image_description = ($default_image_type == "image_intro") ? $external_images->image_intro_caption : $external_images->image_fulltext_caption;
            $secondary_image = ($default_image_type == "image_intro") ? $external_images->image_fulltext : $external_images->image_intro;
            $secondary_image_title = ($default_image_type == "image_intro") ? $external_images->image_fulltext_caption : $external_images->image_intro_caption;
            $secondary_image_description = ($default_image_type == "image_intro") ? $external_images->image_fulltext_caption : $external_images->image_intro_caption;
            $images[] = array('src' => $default_image, 'type' => 'mediabank_image', 'default' => '1', 'title' => $default_image_title, 'description' => $default_image_description);
            if($include_image_intro) $images[] = array('src' => $secondary_image, 'type' => 'mediabank_image', 'default' => '0', 'title' => $secondary_image_title, 'description' => $secondary_image_description);
            $html = str_replace(array('<html><body>', '</body></html>', '<p>&Acirc;&nbsp;</p>'), "", $dom->saveHTML());
            break;

        case "wordpress":
            // future use
            break;
    }
    return array("html" => $html, "images" => $images, "meta" => $meta);
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
        if (is_file($file)){
            if($exclude_zip && pathinfo($file, PATHINFO_EXTENSION) == "zip") continue; // skip zip file
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



