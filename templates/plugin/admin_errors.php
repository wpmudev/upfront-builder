<?php
/*
 * Error checking here
 */

$error = false;
if (empty($_GET['error']) || !is_numeric($_GET['error'])) return false;

$error = (int)$_GET['error'];
if (!$error) return false;

$errors = array(
	Thx_Admin::ERROR_PARAM => __('There was an error processing your request because a parameter was missing on invalid.', UpfrontThemeExporter::DOMAIN),
	Thx_Admin::ERROR_PERMISSION => __('You do not have permissions to do this.', UpfrontThemeExporter::DOMAIN),
	Thx_Admin::ERROR_DEFAULT => __('Oops, something seems to have gone wrong.', UpfrontThemeExporter::DOMAIN),
);
if (!in_array($error, array_keys($errors))) return false;

$error = $errors[$error];
if (empty($error)) return false;

?>
<div class="error">
	<p><?php echo esc_html($error); ?></p>
</div>
