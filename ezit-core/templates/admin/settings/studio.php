<?php
/**
 * Settings section — Site Studio: theme-declared editable fields.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$schemas = EzIT_Studio::schemas();
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        <h3><?php esc_html_e('Site Studio', 'ezit-core'); ?></h3>
    </div>
    <p class="ezit-meta" style="margin-bottom:16px">
        <?php esc_html_e('Content the theme has declared editable — writes go to the same theme mods the Customizer uses, so both editors stay in sync.', 'ezit-core'); ?>
    </p>

    <?php if ($schemas === []) : ?>
        <p class="ezit-meta"><?php esc_html_e('No registered product has declared editable fields yet.', 'ezit-core'); ?></p>
    <?php else :
        foreach ($schemas as $slug => $schema) :
            $title = $schema['title'] ?? $schema['product'];
        ?>
        <h4 style="margin:18px 0 10px;font-size:14.5px">
            <?php echo esc_html($title); ?>
            <span class="ezit-meta" style="font-weight:400"> · <?php echo esc_html($schema['product']); ?></span>
        </h4>
        <?php foreach ($schema['sections'] as $sec_id => $section) : ?>
        <fieldset class="ezit-card" style="margin-bottom:18px">
            <div class="ezit-card__head" style="margin-bottom:10px">
                <h5 style="margin:0;font-size:13.5px"><?php echo esc_html($section['label'] ?? $sec_id); ?></h5>
            </div>
            <?php foreach ($section['fields'] ?? [] as $key => $field) :
                $name  = sprintf('ezit_studio[%s][%s]', $slug, $key);
                $value = EzIT_Studio::value($slug, $key, $schema);
                $label = $field['label'] ?? $key;
            ?>
            <div class="ezit-field" style="margin-bottom:12px">
                <label><?php echo esc_html($label); ?></label>
                <?php if (($field['type'] ?? 'text') === 'textarea') : ?>
                    <textarea name="<?php echo esc_attr($name); ?>" rows="3"><?php echo esc_textarea($value); ?></textarea>
                <?php else : ?>
                    <input type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </fieldset>
        <?php endforeach; ?>
    <?php endforeach; endif; ?>
</div>
