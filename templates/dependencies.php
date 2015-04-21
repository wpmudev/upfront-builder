      <script type="text/javascript">
        Upfront.themeExporter = {
          root: '<?php echo $this->_plugin_dir_url ?>',
          includes: '<?php echo includes_url() ?>js/',
          themes: <?php echo json_encode($themes) ?>,
          templates: [
            { filename: 'archive-home', name: 'Home'},
            { filename: 'single', name: 'Single'}
          ],
          currentTheme: '<?php echo wp_get_theme()->get_stylesheet() ?>'
        };
      </script>
      <script src="<?php echo $this->_plugin_dir_url ?>app/main.js"></script>
