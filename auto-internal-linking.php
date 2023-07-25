<?php
/*
Plugin Name: لینک سازی خودکار
Description: A WordPress plugin to add keywords and associated links with group ID.
Version: 1.0
Author: Mohammad ali Toghani
Author URI: https://toghani.com
*/

// Enqueue the plugin's CSS file
function keyword_linker_enqueue_styles() {
    wp_enqueue_style('keyword-linker-styles', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_style('select2-styles', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
    wp_enqueue_script('select2-script', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
}
add_action('admin_enqueue_scripts', 'keyword_linker_enqueue_styles');


// Create custom database table on plugin activation
function keyword_linker_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_links';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        keyword VARCHAR(255) NOT NULL,
        link VARCHAR(255) NOT NULL,
        group_id INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        INDEX (group_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'keyword_linker_activate');

// Add menu item to the admin dashboard
function keyword_linker_menu() {
    add_menu_page(
        'لینک سازی خودکار',
        'لینک سازی خودکار',
        'manage_options',
        'keyword-linker',
        'keyword_linker_page',
        'dashicons-editor-textcolor',
        25
    );
}
add_action('admin_menu', 'keyword_linker_menu');

function keyword_linker_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_links';

    if (isset($_POST['delete_group']) && isset($_POST['group_id'])) {
        $group_id = intval($_POST['group_id']);
        $wpdb->delete($table_name, array('group_id' => $group_id));
        echo '<div class="notice notice-success"><p>گروه کلمات با موفقیت حذف شد</p></div>';
    }

    if (isset($_POST['delete_keyword']) && isset($_POST['keyword_id'])) {
        $keyword_id = intval($_POST['keyword_id']);
        $wpdb->delete($table_name, array('id' => $keyword_id));
        echo '<div class="notice notice-success"><p>کیورد با موفقیت حذف شد</p></div>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['keyword'])) {
        $keyword = sanitize_text_field($_POST['keyword']);

        // Check if the keyword already exists
        $existing_keyword = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE keyword = %s", $keyword));

        if ($existing_keyword) {
            echo '<div class="notice notice-error"><p>این کیورد قبلاً اضافه شده است!</p></div>';
        } else {
            if (isset($_POST['existing_group']) && intval($_POST['existing_group']) !== 0) {
                $group_id = intval($_POST['existing_group']);
                $link = $wpdb->get_var($wpdb->prepare("SELECT link FROM $table_name WHERE group_id = %d LIMIT 1", $group_id));

                $wpdb->insert(
                    $table_name,
                    array(
                        'keyword' => $keyword,
                        'link' => $link,
                        'group_id' => $group_id
                    )
                );

                echo '<div class="notice notice-success"><p>با موفقیت اضافه شد</p></div>';
            } else {
                $link = esc_url($_POST['link']);

                $existing_keyword_with_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE keyword = %s AND link != ''", $keyword));

                if ($existing_keyword_with_link) {
                    echo '<div class="notice notice-error"><p>کیورد از قبل موجود است!</p></div>';
                } else {
                    $group_id = $wpdb->get_var($wpdb->prepare("SELECT group_id FROM $table_name WHERE link = %s LIMIT 1", $link));

                    // If no group ID found, assign a new group ID
                    if ($group_id === null) {
                        $group_id = $wpdb->get_var("SELECT MAX(group_id) FROM $table_name");
                        $group_id = $group_id !== null ? intval($group_id) + 1 : 1;
                    }

                    $wpdb->insert(
                        $table_name,
                        array(
                            'keyword' => $keyword,
                            'link' => $link,
                            'group_id' => $group_id
                        )
                    );

                    echo '<div class="notice notice-success"><p>با موفقیت اضافه شد</p></div>';
                }
            }
        }
    }

    $groups_per_page = 5;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $groups_per_page;

    echo '
    <div class="wrap keyword-linker">
        <h1>لینک سازی خودکار</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="keyword">کیورد:</label></th>
                    <td><input type="text" id="keyword" name="keyword" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="link">لینک:</label></th>
                    <td><input type="text" id="link" name="link" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="existing_group">گروه موجود:</label></th>
                    <td>
                        <select id="existing_group" name="existing_group" class="keyword-linker-group-select" data-placeholder="انتخاب گروه" required>
                            <option value="0"></option>';

    $existing_groups = $wpdb->get_results("SELECT DISTINCT group_id, link FROM $table_name");

    if (!empty($existing_groups)) {
        foreach ($existing_groups as $group) {
            $group_id = $group->group_id;
            $group_link = $group->link;

            echo '<option value="' . $group_id . '">گروه ' . $group_id . ': ' . urldecode($group_link) . '</option>';
        }
    }

    echo '
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="ذخیره کردن"></p>
        </form>';
    
    
    $total_groups = $wpdb->get_var("SELECT COUNT(DISTINCT group_id) FROM $table_name");

    $total_pages = ceil($total_groups / $groups_per_page);

    $groups_query = $wpdb->get_results("SELECT DISTINCT group_id, link FROM $table_name ORDER BY group_id LIMIT $offset, $groups_per_page");

    if (!empty($groups_query)) {
        foreach ($groups_query as $group_query) {
            $group_id = $group_query->group_id;
            $group_link = $group_query->link;

            $keywords = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE group_id = %d ORDER BY CHAR_LENGTH(keyword) DESC", $group_id));

            echo '
            <div class="keyword-linker-section">
                <h2>آیدی گروه: ' . $group_id . ' 
                    <form method="post" action="" class="delete-group">
                        <input type="hidden" name="group_id" value="' . $group_id . '">
                        <button type="submit" name="delete_group" class="button-secondary">حذف گروه</button>
                    </form>
                </h2>
                <p><strong>لینک گروه:</strong><a href="' . urldecode($group_link) . '" target="_blank">'. urldecode($group_link) .'</a></p>';

            echo '
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">کیورد</th>
                            <th scope="col">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($keywords as $keyword) {
                echo '
                    <tr>
                        <td>' . $keyword->keyword . '</td>
                        <td>
                            <form method="post" action="">
                                <input type="hidden" name="keyword_id" value="' . $keyword->id . '">
                                <button type="submit" name="delete_keyword" class="submitdelete button button-small">حذف</button>
                            </form>
                        </td>
                    </tr>';
            }

            echo '
                    </tbody>
                </table>';

            echo '
            </div>';
        }

        echo '
        <div class="pagination">';

        if ($total_pages > 1) {
            echo '<span class="page-links">';
            if ($current_page > 1) {
                echo '<a href="?page=keyword-linker&paged=' . ($current_page - 1) . '">قبلی</a>';
            }

            for ($page = 1; $page <= $total_pages; $page++) {
                if ($page == $current_page) {
                    echo '<span class="current-page">' . $page . '</span>';
                } else {
                    echo '<a href="?page=keyword-linker&paged=' . $page . '">' . $page . '</a>';
                }
            }

            if ($current_page < $total_pages) {
                echo '<a href="?page=keyword-linker&paged=' . ($current_page + 1) . '">بعدی</a>';
            }
            echo '</span>';
        }

        echo '</div>';
    }

    echo '</div>';

    echo '
    <script>
        jQuery(function($) {
            $(".keyword-linker-group-select").select2({
                allowClear: true,
                width: "50%",
                placeholder: $(this).data("placeholder"),
                language: {
                    noResults: function() {
                        return "نتیجه‌ای یافت نشد";
                    }
                }
            });
        });
    </script>';
}



function keyword_linker_content_filter($content) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_links';

    $keywords = $wpdb->get_results("SELECT * FROM $table_name ORDER BY CHAR_LENGTH(keyword) DESC");

    $linked_keywords = array(); // Keep track of linked keywords for each group

    foreach ($keywords as $keyword) {
        if (in_array($keyword->group_id, $linked_keywords)) {
            continue; // Skip if a keyword from the same group has already been linked
        }

        $keyword_pattern = '/(?<!\pL)' . preg_quote($keyword->keyword, '/') . '(?!\pL)/ui';

        if (preg_match($keyword_pattern, $content)) {
            // Check if the keyword or part of it is inside an HTML <a> tag
            $keyword_in_tag_pattern = '/<a\b[^>]*>(?=.*' . preg_quote($keyword->keyword, '/') . ').*<\/a>/i';
            if (preg_match($keyword_in_tag_pattern, $content)) {
                continue; // Skip linking if keyword or part of it is already inside an <a> tag
            }

            // Extract all alt attributes from image tags
            $img_alt_attributes = array();
            $img_pattern = '/<img\b[^>]*?(alt=(["\'])(.*?)\2)/i';
            preg_match_all($img_pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $alt_text = $match[3];
                $img_alt_attributes[] = $alt_text;
            }

            // Check if the keyword is found in any of the alt attributes
            $keyword_found_in_alt = false;
            foreach ($img_alt_attributes as $alt_text) {
                if (stripos($alt_text, $keyword->keyword) !== false) {
                    $keyword_found_in_alt = true;
                    break;
                }
            }

            if ($keyword_found_in_alt) {
                continue; // Skip linking if keyword is found in the alt attribute of an image tag
            }

            // Link the keyword if it exists in the content
            if (!empty($keyword->link)) {
                $replacement = '<a href="' . $keyword->link . '" target="_blank">' . $keyword->keyword . '</a>';
                $content = preg_replace($keyword_pattern, $replacement, $content, 1);
                $linked_keywords[] = $keyword->group_id; // Mark the group as linked
            }
        }
    }

    return $content;
}



add_filter('the_content', 'keyword_linker_content_filter');

function run_keyword_linker_for_category_description($content) {
    if (is_product_category()) {
        $filtered_content = keyword_linker_content_filter($content);
        return $filtered_content;
    }

    return $content;
}
add_filter('term_description', 'run_keyword_linker_for_category_description');

function run_keyword_linker_for_comments($comment_text) {
    $filtered_comment_text = keyword_linker_content_filter($comment_text);
    return $filtered_comment_text;
}
add_filter('comment_text', 'run_keyword_linker_for_comments');

