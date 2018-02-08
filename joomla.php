<?php

function export() {
    global $include_archived, $messages, $db, $db_prefix, $zip_folder, $zipFile, $loading_file;
    emptyDirectory($zip_folder); // clean up before exporting new content
    @unlink($loading_file);
    $delimiter = ',';
    $enclosure = '"';
    $csv = $category = $header = $filesToZip = array();
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
    $categories = $images = array();
    // get categories
    $table = $db_prefix . 'categories';
    $records = $db->getRecords('select * from `' . $table . '` where `published`="1"');
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
    $records = $db->getRecords('select * from `' . $table . '` where `state` <> -2');
    if (!$records) {
        $messages->addError("No Joomla articles found!");
        return false;
    }
    $loading_counter = 0;
    $articles_count = count($records);
    foreach ($records as $key => $record) {
        $line = $images = array();
        $introtext = $record->introtext;
        $fulltext = $record->fulltext;
        $urls = json_decode($record->urls);
        $article_content = get_article_content($introtext, $fulltext, $urls);
        $external_images = json_decode($record->images);
        $data = extract_data($article_content['body'], $external_images);
        $record->introtext = $article_content['minitext'];
        $record->fulltext = html_entity_decode($data['html'], ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        $record->created = getTimestamp($record->created);
        $record->access = ($record->access <> '1') ? '0' : '1';
        if($include_archived == "1") {
            $record->state = ($record->state == '1' || $record->state == '2') ? '1' : '0';
        }else{
            $record->state = ($record->state <> '1') ? '0' : '1';
        }
        $record->featured = ($record->featured <> '1') ? '0' : '1';
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
        if ($export_type == "full") { // text, images and files must be exported
            foreach ($images as $item) {
                $source = "../" . $item['src'];
                if (is_file($source)) {
                    $destination = $zip_folder . basename($item['src']);
                    copy($source, $destination);
                    $filesToZip[] = $destination;
                }
            }
        }
        $line[] = json_encode($images, JSON_UNESCAPED_UNICODE);
        $csv[] = $line;
        $loading_counter++;
        manage_loading('Scanning content', $articles_count, $loading_counter);
    }
    $csvFile = $zip_folder . 'articles.csv';
    $fp = fopen($csvFile, 'w');
    foreach ($csv as $fields) {
        fputcsv($fp, $fields, $delimiter, $enclosure);
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
}

function get_article_content($introtext, $fulltext, $urls = array()) {
    $article_content = array("minitext" => "", "body" => "");
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
    return $article_content;
}

function extract_data($html, $external_images) {
    $images = array();
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
                $menu_id = $href_data[array_keys($href_data)[0]]; // get category id from menu_id
                $category_id = get_category_id($menu_id);
                $category_name = get_category_name($category_id);
                if ($category_name) { // if a valid category name has been extracted
                    $href = "http://odyssey.cms?category=" . urlencode($category_name);
                    $node->setAttribute("href", $href);
                }
            }
            if (preg_match('/^index.php\?option=com_content&view=article&id=/', $href)) { // set article link
                parse_str($href, $href_data);
                $article_id = $href_data["id"];
                $article_name = get_article_name($article_id);
                if ($article_name) { // if a valid article name has been extracted
                    $href = "http://odyssey.cms?article=" . urlencode($article_name);
                    $node->setAttribute("href", $href);
                }
            }
            if (preg_match('/^images\//', $href)) { // its other file for mediabank
                $img_id = uniqid() . mt_rand(1000, 10000);
                $images[] = array(
                    'src' => $href,
                    'type' => 'file',
                    'default' => '0',
                    'title' => $node->textContent,
                    'description' => '',
                    'img_id' => $img_id
                );
                $node->setAttribute("href", "http://odyssey.cms?file=" . $img_id);
            }
        }else{ // scan for video links
            if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $href, $result) ||
                preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $href, $result) ||
                preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $href, $result) ||
                preg_match('/youtu\.be\/([^\&\?\/]+)/', $href, $result)) {
                $videoCode = $result[1];
                if ($videoCode) {
                    $provider = "youtube";
                    $href = "http://odyssey.cms?video=" . $provider . "videocode" . urlencode($videoCode);
                    $node->setAttribute("href", $href);
                }
            }
            if (preg_match('/vimeo\.com/', $href)) {
                list($videoCode, $null) = explode("?", $href);
                list($videoCode,) = explode("_", $videoCode);
                $temp = explode("/", $videoCode);
                $videoCode = $temp[count($temp) - 1];
                if ($videoCode) {
                    $provider = "vimeo";
                    $href = "http://odyssey.cms?video=" . $provider . "videocode" . urlencode($videoCode);
                    $node->setAttribute("href", $href);
                }
            }
            if (preg_match('/dailymotion\.com/', $href) || preg_match('/dai\.ly/', $href)) {
                list($videoCode,) = explode("?", $href);
                list($videoCode,) = explode("_", $videoCode);
                $temp = explode("/", $videoCode);
                $videoCode = $temp[count($temp) - 1];
                if ($videoCode) {
                    $provider = "dailymotion";
                    $href = "http://odyssey.cms?video=" . $provider . "videocode" . urlencode($videoCode);
                    $node->setAttribute("href", $href);
                }
            }
        }
    }
    $tags = $dom->getElementsByTagName('img');
    foreach ($tags as $key => $tag) {
        $node = $tags->item($key);
        $src = $node->getAttribute('src');
        if (!preg_match('/^http/', $src)) { // its a local image
            $img_id = uniqid() . mt_rand(1000, 10000);
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
    return array("html" => $html, "images" => $images);
}

function get_category_id($menu_id) {
    global $db, $db_prefix;
    $table = $db_prefix . "menu";
    $menu_id = (int)$menu_id; // sanitize
    $q = "select `link` from `$table` where `id`='$menu_id'";
    $link = $db->getRecord($q)->link;
    parse_str($link, $link_data);
    $category_id = $link_data["id"];
    return $category_id;
}

function get_article_name($id) {
    global $db, $db_prefix;
    $table = $db_prefix . "content";
    $id = (int)$id; // sanitize
    $q = "select `title` from `$table` where `id`='$id'";
    $article_name = $db->getRecord($q)->title;
    return $article_name;
}

function get_category_name($id) {
    global $db, $db_prefix;
    $table = $db_prefix . "categories";
    $id = (int)$id; // sanitize
    $q = "select `title` from `$table` where `id`='$id'";
    $category_name = $db->getRecord($q)->title;
    return $category_name;
}