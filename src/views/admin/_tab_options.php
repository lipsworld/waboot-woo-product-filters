<p xmlns="http://www.w3.org/1999/html">
    <?php _e("Here you can customize some of the plugin behaviors.",$textdomain); ?>
</p>

<form action="" method="post">
    <h3><?php _e("Active filters settings",$textdomain); ?></h3>
    <?php foreach ($current_settings['filters_params'] as $filter_slug => $filter_params): ?>
        <h4><?php echo $filter_slug; ?></h4>
        <select name="wbwpf_options[filters_params][<?php echo $filter_slug; ?>][uiType]">
            <?php foreach ($available_uiTypes as $uiType_slug => $uiType_class) : ?>
                <?php $selected = isset($current_settings[$filter_slug]['uiType']) && $current_settings[$filter_slug]['uiType'] == $uiType_slug; ?>
                <option value="<?php echo $uiType_slug; ?>" <?php if($selected): ?>selected<?php endif; ?>><?php echo $uiType_slug; ?></option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>
    <h3><?php _e("Catalog settings",$textdomain); ?></h3>
    <label style="display: block; margin-bottom: 1em;">
        <input type="checkbox" value="1" name="wbwpf_options[show_variations]">
	    <?php _e("Show variations alongside products.",$textdomain); ?>
    </label>
    <label style="display: block; margin-bottom: 1em;">
        <input type="checkbox" value="1" name="wbwpf_options[hide_parent_products]">
	    <?php _e("Hide products with variations.",$textdomain); ?>
    </label>
</form>