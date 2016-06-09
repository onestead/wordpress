<?php

/**
 * category-parent-children-checker
 */
function my_site__admin_style_category_toggler() {
    $taxonomies = apply_filters('my_site__admin_style_category_toggler',array());
    for ($x=0; $x < count($taxonomies); $x++)
        $taxonomies[$x] = '#'.$taxonomies[$x].'div .selectit input';
    $selector = implode(',',$taxonomies);
    if ($selector == '')
        $selector = '.selectit input';
    echo '
<script type="text/javascript">
jQuery("'.$selector.'").change(function() {
    var checkbox = jQuery(this);
    if (checkbox && checkbox.length !== 0) {
        var li = checkbox.closest("li");
        if (li && li.length !== 0) {
            if (li.has("ul").length !== 0)
                li.find(":checkbox").not(this).prop("checked", this.checked);
            checkParentNodes(checkbox.prop("checked"), checkbox);
        } else { /****/ }
    } else { /****/ }
});
function checkParentNodes(checked, checkbox) {
    if (checkbox && checkbox.length !== 0) {
        var ul = checkbox.closest("li").parent("ul");
        if (ul && ul.length !== 0) {
            var parent = ul.prev("label").children("input[type=checkbox]");
            if (parent && parent.length !== 0) {
                if (checked === true) {
                    parent.prop("checked", checked);
                    checkParentNodes(checked, parent);
                } else {
                    var checkedAll = false;
                    ul.find(":checkbox").each(function() {
                        checkedAll|= true === jQuery(this).prop("checked"); });
                    if (!checkedAll) {
                        parent.prop("checked", checked);
                        checkParentNodes(checked, parent);
                    } else { /****/ }
                }
            } else { /****/ }
        } else { /****/ }
    } else { /****/ }
}
</script>
';
}

if (is_admin()) {
    add_action('admin_footer-post.php', 'my_site__admin_style_category_toggler', 999, 0);
    add_action('admin_footer-post-new.php', 'my_site__admin_style_category_toggler', 999, 0);
    add_action('wp_terms_checklist_args', function($args, $post_id) {
        /** terms order keep **/
        $args['checked_ontop'] = false;
        return $args;
    }, 999, 2);
} else {
    /** theme **/
}

