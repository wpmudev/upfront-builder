      <script type="text/javascript">
        Upfront.themeExporter = {
          root: '<?php echo $root_url; ?>',
          includes: '<?php echo $includes_url; ?>',
          themes: <?php echo json_encode($themes) ?>,
          templates: [
            { filename: 'archive-home', name: 'Home'},
            { filename: 'single', name: 'Single'}
          ],
          currentTheme: '<?php echo wp_get_theme()->get_stylesheet() ?>',
          current_layout_label: <?php echo json_encode($layout); ?>
        };
      </script>
      <script src="<?php echo $root_url ?>app/main.js"></script>
