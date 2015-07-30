/*global module, require */
module.exports = function(grunt) {
	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-wp-i18n');

	grunt.initConfig({
		makepot: {
			target: {
				options: {
					domainPath: 'languages/',
					type: 'wp-plugin'
				}
			}
		},
		sass: {
			options: {
				sourceMap: true,
				outputStyle: 'nested',
				sourceComments: false
			},
			dist: {
				files: {
					'styles/exporter.css': 'styles/sass/exporter.scss'
				}
			}
		}
	});

	grunt.registerTask('default', ['sass']);
};
