<?php

define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require('../wp-load.php');
setErrorReporting(); // restore Odyssey error reporting levels


function export() {
    global $messages, $db, $db_prefix, $zip_folder, $zipFile, $loading_file, $categories, $images;
    emptyDirectory($zip_folder); // clean up before exporting new content
    @unlink($loading_file);
    $delimiter = ',';
    $enclosure = '"';
    $csv = $categories = $images = $category = $header = $filesToZip = array();
    $simple_fields = array('post_title', 'post_excerpt', 'post_content', 'post_date');
    $header['post_title'] = "Τίτλος";
    $header['post_excerpt'] = "Συνοπτική περιγραφή";
    $header['post_content'] = "Περιεχόμενο του άρθρου";
    $header['category'] = "Κατηγορία δημοσίευσης";
    $header['post_date'] = "Ημερομηνία δημοσίευσης";
    $header['active'] = "Δημοσιευμένο";
    $header['featured'] = "Με χαρακτηριστική προβολή";
    $header['access'] = "Επίπεδο πρόσβασης";
    $header['images'] = "Εικόνες";
    $csv[] = $header;
    // get categories
    $terms_table = $db_prefix . 'terms';
    $taxonomy_table = $db_prefix . 'term_taxonomy';
    $q = "select t.term_id, t.name, tt.description from $terms_table as t, $taxonomy_table as tt where t.term_id=tt.term_id and tt.taxonomy='category'";
    $records = $db->getRecords($q);
    if (!$records) {
        $messages->addError("No Wordpress categories found!");
        return false;
    }
    foreach ($records as $key => $record) {
        $category['title'] = $record->name;
        $category['description'] = $record->description;
        $categories[$record->id] = $category;
    }
    // get posts
    $table = $db_prefix . 'posts';
    $posts = $db->getRecords('select * from `' . $table . '` where (`post_type`="post" or `post_type`="page") and (`post_status` != "auto-draft" and `post_status` != "trash")');
    if (!count($posts)) {
        $messages->addError("No Wordpress posts/pages found!");
        return false;
    }
    // start getting posts
    $counter = 0;
    $articles_count = count($posts);
    foreach ($posts as $key => $post) {
        $post_id = $post->ID;
        $post_type = $post->post_type;
        $line = $images = array();
        $minitext = $post->post_excerpt;
        $body = $post->post_content;
        $article_content = get_article_content($minitext, $body);
        $external_image = get_external_image($post_id);
        $data = extract_data($article_content['body'], $external_image);
        $post->post_excerpt = $article_content['minitext'];
        $post->post_content = html_entity_decode($data['html'], ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        $post->post_date = getTimestamp($post->post_date);
        $catID = get_category_id($post_id);
        $images = $data['images'];
        foreach ($header as $key => $val) {
            if (in_array($key, $simple_fields)) {
                $line[] = trim($post->$key);
            }
            if ($key == "category") {
                $categoryTitle = ($post_type == "page") ? "__PAGE__" : get_category_name($catID);
                $line[] = trim($categoryTitle);
            }
            if ($key == "active") {
                $active = ($post->post_status == "publish" || $post->post_status == "private") ? 1 : 0;
                $active = (!empty($post->post_password)) ? 0 : $active;
                $line[] = $active;
            }
            if ($key == "access") {
                $access = ($post->post_status == "publish") ? 1 : 0;
                $access = (!empty($post->post_password)) ? 0 : $access;
                $line[] = $access;
            }
            if ($key == "featured") {
                $featured = 0; // no featured posts in Wordpress
                $line[] = $featured;
            }
        }
        global $export_type;
        if ($export_type == "full") { // text, images and files must be exported
            foreach ($images as $item) {
                $source = end(explode("wp-content/uploads/", $item['src']));
                $source = "../wp-content/uploads/" . $source;
                if (is_file($source)) {
                    $destination = $zip_folder . basename($source);
                    copy($source, $destination);
                    $filesToZip[] = $destination;
                }
            }
        }
        $line[] = json_encode($images, JSON_UNESCAPED_UNICODE);
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
}

function get_article_content($minitext, $body) {
    $article_content = array("minitext" => "", "body" => "");
    $hasEmbedMinitext = strpos($body, '<!--more-->') !== false;
    if ($hasEmbedMinitext) {
        list($minitext, $body) = explode('<!--more-->', $body);
        $minitext = limit_text(strip_tags(shortcodes2tags($minitext)));
    } else {
        $minitext = ($minitext) ? limit_text(strip_tags(shortcodes2tags($minitext))) : limit_text(strip_tags(shortcodes2tags($body)));
    }
    $article_content['minitext'] = nl2br($minitext);
    $article_content['body'] = nl2br($body);
    return $article_content;
}

function extract_data($html, $image) {
    global $export_type;
    $images = $files_added = $ids_added = array();
    if ($export_type == "full" && !empty($image['image_url'])) { // include default image
        $img_id = uniqid() . mt_rand(1000, 10000);
        $files_added[] = $image['image_url'];
        $ids_added[] = $img_id;
        $images[] = array(
            'src' => $image['image_url'],
            'type' => 'mediabank_image',
            'default' => '1',
            'title' => $image['image_title'],
            'description' => $image['image_description'],
            'img_id' => $img_id
        );
    }
    $html = shortcodes2tags($html);
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
    $tags = $dom->getElementsByTagName('a');
    foreach ($tags as $key => $tag) {
        $node = $tags->item($key);
        $href = $node->getAttribute('href');
        $post_id = url_to_postid($href);
        if ($post_id) { // it's an internal link to post or page
            $article_name = get_article_name($post_id);
            if ($article_name) { // if a valid article name has been extracted
                $isPage = isPage($post_id);
                $href = ($isPage) ? "http://odyssey.cms?page=" . urlencode($article_name) : "http://odyssey.cms?article=" . urlencode($article_name);
                $node->setAttribute("href", $href);
            }
            continue;
        } else {
            if (preg_match('/\?cat=/', $href)) {
                //  Plain cat link! A category link must be set
                parse_str($href, $href_data);
                $category_id = $href_data[array_keys($href_data)[0]];
                $category_name = get_category_name($category_id);
                if ($category_name) { // if a valid category name has been extracted
                    $href = "http://odyssey.cms?category=" . urlencode($category_name);
                    $node->setAttribute("href", $href);
                    continue;
                }
            }
            $category_name = get_category_name(end(explode("/", rtrim($href, '/')))); // try if we can get category name from slug
            if ($category_name) { //  SEF URL cat link! A category link must be set
                $href = "http://odyssey.cms?category=" . urlencode($category_name);
                $node->setAttribute("href", $href);
                continue;
            }
            $file_name = get_media_filename(end(explode("/", rtrim($href, '/')))); // try if it's a valid media filename
            if ($file_name) {
                $title = ($node->textContent == $href) ? $file_name : $node->textContent;
                $index = array_search($file_name, $files_added);
                if(!$index){
                    $img_id = uniqid() . mt_rand(1000, 10000);
                    $files_added[] = $file_name;
                    $ids_added[] = $img_id;
                    $images[] = array(
                        'src' => $file_name,
                        'type' => 'file',
                        'default' => '0',
                        'title' => $title,
                        'description' => '',
                        'img_id' => $img_id
                    );
                }else{
                    $img_id = $ids_added[$index];
                }
                $node->setAttribute("href", "http://odyssey.cms?file=" . $img_id);
                $node->nodeValue = $title;
                continue;
            }
            if (preg_match('/wp-content\/uploads/', $href)) { // its a relative link
                $index = array_search($href, $files_added);
                $title = ($node->textContent == $href) ? basename($href) : $node->textContent;
                if(!$index){
                    $img_id = uniqid() . mt_rand(1000, 10000);
                    $files_added[] = $href;
                    $ids_added[] = $img_id;
                    $images[] = array(
                        'src' => $href,
                        'type' => 'file',
                        'default' => '0',
                        'title' => $title,
                        'description' => '',
                        'img_id' => $img_id
                    );
                }else{
                    $img_id = $ids_added[$index];
                }
                $node->setAttribute("href", "http://odyssey.cms?file=" . $img_id);
                $node->nodeValue = $title;
                continue;
            } else { // scan for video links
                if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $href, $result) ||
                    preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $href, $result) ||
                    preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $href, $result) ||
                    preg_match('/youtu\.be\/([^\&\?\/]+)/', $href, $result)
                ) {
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
    }
    $tags = $dom->getElementsByTagName('img');
    foreach ($tags as $key => $tag) {
        $node = $tags->item($key);
        $src = $node->getAttribute('src');
        if (preg_match('/wp-content\/uploads/', $src)) { // its a local image
            $img_src = end(explode("wp-content/uploads/", $src));
            $src = get_site_url() . "/wp-content/uploads/" . $img_src;
            $index = array_search($src, $files_added);
            if(!$index){
                $img_id = uniqid() . mt_rand(1000, 10000);
                $files_added[] = $src;
                $ids_added[] = $img_id;
                $image_type = ($node->getAttribute('data-type') == "gallery") ? "mediabank_image" : "body_image";
                $images[] = array(
                    'src' => $src,
                    'type' => $image_type,
                    'default' => '0',
                    'title' => get_media_title($img_src),
                    'description' => get_media_description($img_src),
                    'img_id' => $img_id
                );
            }else{
                $img_id = $ids_added[$index];
            }
            $node->setAttribute("src", "http://odyssey.cms?image=" . $img_id);
        }
    }
    global $export_type;
    if ($export_type == "full" && !empty($image['image_url'])) { // text & images must be exported
        $images[] = array('src' => $image['image_url'], 'type' => 'mediabank_image', 'default' => '1', 'title' => $image['image_title'], 'description' => $image['image_description']);
    }
    $html = str_replace(array(
        '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">',
        '<html><body>',
        '</body></html>'
    ), "", $dom->saveHTML());
    return array("html" => $html, "images" => $images);
}

function shortcodes2tags($html) {
    // scan for videos in [video] shortcodes
    preg_match_all('/\[video ([a-zA-Z0-9-_ =":\/\/\.]+)/', $html, $videos);
    if (count($videos[0])) { // we have results
        foreach ($videos[0] as $key => $value) $videos[0][$key] = $value . "][/video]";
        foreach ($videos[1] as $key => $value) {
            $pairs = explode(" ", $value);
            foreach ($pairs as $pair) {
                list(, $attr_value) = explode("=", $pair);
                if (preg_match('/wp-content\/uploads/', $attr_value)) {
                    $video_file = str_replace('"', '', $attr_value);
                    $link = '<div> <a href="' . $video_file . '">video</a> </div>';
                    $shortcode = $videos[0][$key];
                    $html = str_replace($shortcode, $link, $html);
                }
            }
        }
    }
    // scan for audios in [audio] shortcodes
    preg_match_all('/\[audio ([a-zA-Z0-9-_ =":\/\/\.]+)/', $html, $audios);
    if (count($audios[0])) { // we have results
        foreach ($audios[0] as $key => $value) $audios[0][$key] = $value . "][/audio]";
        foreach ($audios[1] as $key => $value) {
            $pairs = explode(" ", $value);
            foreach ($pairs as $pair) {
                list(, $attr_value) = explode("=", $pair);
                if (preg_match('/wp-content\/uploads/', $attr_value)) {
                    $audio_file = str_replace('"', '', $attr_value);
                    $link = '<div> <a href="' . $audio_file . '">audio</a> </div>';
                    $shortcode = $audios[0][$key];
                    $html = str_replace($shortcode, $link, $html);
                }
            }
        }
    }
    // scan for images/files in [gallery] shortcodes
    preg_match_all('/\[gallery ([a-zA-Z0-9-_ =,":\/\/\.]+)/', $html, $galleries);
    if (count($galleries[0])) { // we have results
        foreach ($galleries[0] as $key => $value) $galleries[0][$key] = $value . "]";
        foreach ($galleries[1] as $key => $value) {
            $pairs = explode(" ", $value);
            foreach ($pairs as $pair) {
                list($attr, $attr_value) = explode("=", $pair);
                if ($attr == "ids") {
                    $attr_value = str_replace('"', '', $attr_value);
                    $gallery_ids = explode(",", $attr_value);
                    foreach ($gallery_ids as $gallery_id) {
                        $filename = get_site_url() . "/wp-content/uploads/" . get_media_filename($gallery_id);
                        $img = '<div> <img data-type="gallery" src="' . $filename . '"> </div>';
                        $shortcode = $galleries[0][$key];
                        $html = str_replace($shortcode, $shortcode . $img, $html);
                    }
                    $html = str_replace($shortcode, "", $html);
                }
            }
        }
    }
    // scan for videos in [embed] shortcodes
    preg_match_all('/\[embed ([a-zA-Z0-9-_ =":\/\/\.\]\?\=]+)/', $html, $embeds);
    if (count($embeds[0])) { // we have results
        foreach ($embeds[0] as $key => $value) $embeds[0][$key] = $value . "[/embed]";
        foreach ($embeds[1] as $key => $value) {
            $data = explode("http", $value);
            $url = "http" . $data[1];
            $link = '<div> <a href="' . $url . '" target="_blank">' . $url . '</a> </div>';
            $shortcode = $embeds[0][$key];
            $html = str_replace($shortcode, $link, $html);
        }
    }
    // scan for audios/videos in [playlist] shortcodes
    preg_match_all('/\[playlist ([a-zA-Z0-9-_ =,":\/\/\.]+)/', $html, $playlists);
    if (count($playlists[0])) { // we have results
        foreach ($playlists[0] as $key => $value) $playlists[0][$key] = $value . "]";
        foreach ($playlists[1] as $key => $value) {
            $pairs = explode(" ", $value);
            $hasIDs = false;
            foreach ($pairs as $pair) {
                if (preg_match('/ids/', $pair)) $hasIDs = true;
            }
            if (!$hasIDs) continue; // skip playlists without ids attribute
            foreach ($pairs as $pair) {
                list($attr, $attr_value) = explode("=", $pair);
                if ($attr == "ids") {
                    $attr_value = str_replace('"', '', $attr_value);
                    $playlist_ids = explode(",", $attr_value);
                    foreach ($playlist_ids as $playlist_id) {
                        $filename = get_site_url() . "/wp-content/uploads/" . get_media_filename($playlist_id);
                        $link = '<div> <a data-type="playlist" href="' . $filename . '">' . $filename . '</a> </div>';
                        $shortcode = $playlists[0][$key];
                        $html = str_replace($shortcode, $shortcode . $link, $html);
                    }
                    $html = str_replace($shortcode, "", $html);
                }
            }
        }
    }
    $html = str_replace("[[", "[", str_replace("]]", "]", $html)); // restore escaped shortcode tags
    return $html;
}

function get_category_id($post_id) {
    global $db, $db_prefix;
    $rel_table = $db_prefix . "term_relationships";
    $tax_table = $db_prefix . "term_taxonomy";
    $post_id = (int)$post_id; // sanitize
    $q = "select rel.term_taxonomy_id as cat_id from $rel_table as rel, $tax_table as tax 
            where tax.taxonomy='category' 
            and tax.term_taxonomy_id=rel.term_taxonomy_id 
            and rel.object_id='$post_id'";
    $category_id = $db->getRecord($q)->cat_id;
    return $category_id;
}

function get_article_name($id) {
    global $db, $db_prefix;
    $table = $db_prefix . "posts";
    $id = (int)$id; // sanitize
    $q = "select `post_title` from `$table` where `ID`='$id'";
    $article_name = $db->getRecord($q)->post_title;
    return $article_name;
}

function isPage($post_id) {
    global $db, $db_prefix;
    $table = $db_prefix . "posts";
    $q = "select `post_type` from `$table` where `ID`='$post_id'";
    $post_type = $db->getRecord($q)->post_type;
    return ($post_type == "page");
}

function get_category_name($cat_id) {
    global $db, $db_prefix;
    $table = $db_prefix . "terms";
    if (is_numeric($cat_id)) {
        $cat_id = (int)$cat_id; // sanitize
        $q = "select `name` from `$table` where `term_id`='$cat_id'";
    } else {
        $slug = $cat_id; // try as slug
        $q = "select `name` from `$table` where `slug`='$slug'";
    }
    $category_name = $db->getRecord($q)->name;
    return $category_name;
}

function get_external_image($post_id) {
    global $db, $db_prefix;
    $posts_table = $db_prefix . "posts";
    $posts_meta_table = $db_prefix . "postmeta";
    $post_id = (int)$post_id; // sanitize
    $thumbnail_id = get_thumbnail_id($post_id);
    $q = "select m.meta_value as image_url, p.post_name as image_title, p.post_content as image_content,  p.post_excerpt as image_excerpt 
            from `$posts_meta_table` as m, `$posts_table` as p where 
            m.meta_key='_wp_attached_file' and  
            m.post_id='$thumbnail_id' and 
            p.post_parent='$post_id' and 
            p.ID=m.post_id";
    $image = $db->getRecord($q);
    if(!$image) return false;
    $image_url = get_site_url() . "/wp-content/uploads/" . $image->image_url;
    $image_title = $image->image_title;
    $image_description = ($image->image_excerpt) ? $image->image_excerpt : $image->image_content;
    $image_data = array('image_url' => $image_url, 'image_title' => $image_title, 'image_description' => $image_description);
    return $image_data;
}


function get_thumbnail_id($post_id) {
    global $db, $db_prefix;
    $posts_meta_table = $db_prefix . "postmeta";
    $q = "select meta_value from $posts_meta_table where meta_key='_thumbnail_id' and post_id='$post_id'";
    $thumbnail_id = $db->getRecord($q)->meta_value;
    return $thumbnail_id;
}

function get_media_filename($id) {
    global $db, $db_prefix;
    $posts_meta_table = $db_prefix . "postmeta";
    if (is_numeric($id)) {
        $q = "select meta_value as filename from $posts_meta_table where meta_key='_wp_attached_file' and post_id='$id'";
    } else {
        $file_name = $id; // try if it's actually a valid media filename
        $q = "select meta_value as filename from $posts_meta_table where meta_key='_wp_attached_file' and meta_value='$file_name'";
    }
    $filename = $db->getRecord($q)->filename;
    return $filename;
}

function get_media_title($filename){
    global $db, $db_prefix;
    $posts_table = $db_prefix . "posts";
    $posts_meta_table = $db_prefix . "postmeta";
    $q="select p.post_title, p.post_content, p.post_excerpt from $posts_table as p, $posts_meta_table as pm where 
          pm.meta_key ='_wp_attached_file' and 
          pm.meta_value = '$filename' and 
          p.post_type = 'attachment' and 
          pm.post_id = p.ID";
    $data = $db->getRecord($q);
    if($data->post_title) return $data->post_title; // first check for title
    if($data->post_excerpt) return $data->post_excerpt; // else for post_excerpt
    if($data->post_content) return $data->post_content; // or for post_content
    return $filename; // no other title found so use the filename as title
}

function get_media_description($filename){
    global $db, $db_prefix;
    $posts_table = $db_prefix . "posts";
    $posts_meta_table = $db_prefix . "postmeta";
    $q="select p.post_content, p.post_excerpt from $posts_table as p, $posts_meta_table as pm where 
          pm.meta_key ='_wp_attached_file' and 
          pm.meta_value = '$filename' and 
          p.post_type = 'attachment' and 
          pm.post_id = p.ID";
    $data = $db->getRecord($q);
    if($data->post_excerpt) return $data->post_excerpt; // first check for any description in post_excerpt
    if($data->post_content) return $data->post_content; // else for post_content
    if($data->post_title) return $data->post_title;// or for post_title
    return $filename; // no other description found so use the filename as description
}