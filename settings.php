<?php
/**
 * Keeps theme settings providing simple interface for getting
 * stored settings, updating and saving settings to file.
 */
class UfExThemeSettings
{
	protected $filePath;
	protected $settings;

	public function __construct($themeStylesheet) {
		$this->filePath = sprintf(
			'%ssettings.php',
			$themeStylesheet
		);

		if (file_exists($this->filePath)) {
			$this->settings = include $this->filePath;
		}
	}

	public function get($name) {
		return isset($this->settings[$name]) ? $this->settings[$name] : null;
	}

	public function set($name, $value) {
		$this->settings[$name] = $value;
		$this->save();
	}

	protected function save() {
		$fileContents = "<?php\nreturn array(";
		foreach($this->settings as $setting=>$value) {
			$fileContents .= "\t'$setting' => '$value',\n";
		}
		$fileContents .= ");";

		file_put_contents($this->filePath, $fileContents);
	}
}
