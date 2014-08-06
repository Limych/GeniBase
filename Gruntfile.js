/* jshint node:true */
module.exports = function(grunt) {
	var path = require('path'),
		SOURCE_DIR = 'src/',
		BUILD_DIR = 'build/';

	// Load tasks.
	require('matchdep').filterDev(['grunt-*', '!grunt-legacy-util']).forEach( grunt.loadNpmTasks );
	// Load legacy utils
	grunt.util = require('grunt-legacy-util');

	// Project configuration.
	grunt.initConfig({
		autoprefixer: {
			options: {
				browsers: ['Android >= 2.1', 'Chrome >= 21', 'Explorer >= 7', 'Firefox >= 17', 'Opera >= 12.1', 'Safari >= 6.0']
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: SOURCE_DIR,
				src: [
					'gb-admin/css/*.css',
					'gb/css/*.css'
				]
			},
		},
		clean: {
			all: [BUILD_DIR],
			dynamic: {
				dot: true,
				expand: true,
				cwd: BUILD_DIR,
				src: []
			},
			qunit: ['tests/qunit/compiled.html']
		},
		copy: {
			files: {
				files: [
					{
						dot: true,
						expand: true,
						cwd: SOURCE_DIR,
						src: [
							'**',
							'!**/.{svn,git}/**', // Ignore version control directories.
							// Ignore unminified versions of external libs we don't ship:
							'!gb/js/backbone.js',
							'!gb/js/underscore.js',
							'!gb/js/jquery/jquery.masonry.js',
							'!gb/version.php' // Exclude version.php
						],
						dest: BUILD_DIR
					},
					{
						src: 'gb-config-sample.php',
						dest: BUILD_DIR
					}
				]
			},
			'gb-admin-rtl': {
				options: {
					processContent: function( src ) {
						return src.replace( /\.css/g, '-rtl.css' );
					}
				},
				src: SOURCE_DIR + 'gb-admin/css/gb-admin.css',
				dest: BUILD_DIR + 'gb-admin/css/gb-admin-rtl.css'
			},
			version: {
				options: {
					processContent: function( src ) {
						return src.replace( /^\$gb_version = '(.+?)';/m, function( str, version ) {
							version = version.replace( /-src$/, '' );

							// If the version includes an SVN commit (-12345), it's not a released alpha/beta. Append a date.
							version = version.replace( /-[\d]{5}$/, '-' + grunt.template.today( 'yyyymmdd' ) );

							/* jshint quotmark: true */
							return "$gb_version = '" + version + "';";
						});
					}
				},
				src: SOURCE_DIR + 'gb/version.php',
				dest: BUILD_DIR + 'gb/version.php'
			},
			dynamic: {
				dot: true,
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				src: []
			},
			qunit: {
				src: 'tests/qunit/index.html',
				dest: 'tests/qunit/compiled.html',
				options: {
					processContent: function( src ) {
						return src.replace( /([^\.])*\.\.\/src/ig , '/../build' );
					}
				}
			}
		},
		sass: {
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '.css',
				src: [ 'gb/css/*.scss' ],
				options: {
					outputStyle: 'expanded'
				}
			}
		},
		cssmin: {
			options: {
//				'gb-admin': ['gb-admin', 'color-picker', 'customize-controls', 'customize-widgets', 'ie', 'install', 'login', 'deprecated-*']
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '.min.css',
				src: [
//					'gb-admin/css/{<%= cssmin.options["gb-admin"] %>}.css',
					'gb/css/*.css'
				]
			},
			rtl: {
				expand: true,
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				ext: '.min.css',
				src: [
//					'gb-admin/css/{<%= cssmin.options["gb-admin"] %>}-rtl.css',
					'gb/css/*-rtl.css'
				]
			},
		},
		cssjanus: {
			core: {
				options: {
					swapLtrRtlInUrl: false,
					processContent: function( src ) {
						return src.replace( /url\((.+?)\.css\)/g, 'url($1-rtl.css)' );
					}
				},
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '-rtl.css',
				src: [
//					'gb-admin/css/*.css',
					'gb/css/*.css'
				]
			},
			dynamic: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '-rtl.css',
				src: []
			}
		},
		jshint: {
			options: grunt.file.readJSON('.jshintrc'),
			grunt: {
				src: ['Gruntfile.js']
			},
			tests: {
				src: [
					'tests/qunit/**/*.js',
					'!tests/qunit/vendor/*',
					'!tests/qunit/editor/**'
				],
				options: grunt.file.readJSON('tests/qunit/.jshintrc')
			},
			themes: {
				expand: true,
				cwd: SOURCE_DIR + 'gb-content/themes',
				src: [
					'twenty*/**/*.js',
					'!twenty{eleven,twelve,thirteen}/**',
					// Third party scripts
					'!twentyfourteen/js/html5.js'
				]
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				src: [
					'gb-admin/js/*.js',
					'gb/js/*.js',
					// GeniBase scripts inside directories
					'gb/js/jquery/jquery.table-hotkeys.js',
					'gb/js/mediaelement/gb-mediaelement.js',
					'gb/js/plupload/handlers.js',
					'gb/js/plupload/gb-plupload.js',
					// Third party scripts
					'!gb/js/backbone*.js',
					'!gb/js/swfobject.js',
					'!gb/js/underscore*.js',
					'!gb/js/colorpicker.js',
					'!gb/js/hoverIntent.js',
					'!gb/js/json2.js',
					'!gb/js/tw-sack.js',
					'!**/*.min.js'
				],
				// Remove once other JSHint errors are resolved
				options: {
					curly: false,
					eqeqeq: false
				},
				// Limit JSHint's run to a single specified file:
				//
				//    grunt jshint:core --file=filename.js
				//
				// Optionally, include the file path:
				//
				//    grunt jshint:core --file=path/to/filename.js
				//
				filter: function( filepath ) {
					var index, file = grunt.option( 'file' );

					// Don't filter when no target file is specified
					if ( ! file ) {
						return true;
					}

					// Normalize filepath for Windows
					filepath = filepath.replace( /\\/g, '/' );
					index = filepath.lastIndexOf( '/' + file );

					// Match only the filename passed from cli
					if ( filepath === file || ( -1 !== index && index === filepath.length - ( file.length + 1 ) ) ) {
						return true;
					}

					return false;
				}
			},
			plugins: {
				expand: true,
				cwd: SOURCE_DIR + 'gb-content/plugins',
				src: [
					'**/*.js',
					'!**/*.min.js'
				],
				// Limit JSHint's run to a single specified plugin directory:
				//
				//    grunt jshint:plugins --dir=foldername
				//
				filter: function( dirpath ) {
					var index, dir = grunt.option( 'dir' );

					// Don't filter when no target folder is specified
					if ( ! dir ) {
						return true;
					}

					dirpath = dirpath.replace( /\\/g, '/' );
					index = dirpath.lastIndexOf( '/' + dir );

					// Match only the folder name passed from cli
					if ( -1 !== index ) {
						return true;
					}

					return false;
				}
			}
		},
		qunit: {
			files: [
				'tests/qunit/**/*.html',
				'!tests/qunit/editor/**'
			]
		},
		phpunit: {
			'default': {
				cmd: 'phpunit',
				args: ['-c', 'phpunit.xml.dist']
			},
			ajax: {
				cmd: 'phpunit',
				args: ['-c', 'phpunit.xml.dist', '--group', 'ajax']
			},
		},
		uglify: {
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '.min.js',
				src: [
					'gb-admin/js/*.js',
					'gb/js/*.js',
					'gb/js/plupload/handlers.js',
					'gb/js/plupload/gb-plupload.js',
					'gb/js/tinymce/plugins/wordpress/plugin.js',
					'gb/js/tinymce/plugins/wp*/plugin.js',

					// Exceptions
					'!gb-admin/js/custom-header.js', // Why? We should minify this.
					'!gb-admin/js/farbtastic.js',
					'!gb-admin/js/iris.min.js',
					'!gb/js/backbone.min.js',
					'!gb/js/swfobject.js',
					'!gb/js/underscore.min.js',
					'!gb/js/zxcvbn.min.js'
				]
			}
		},
		concat: {
//			tinymce: {
//				options: {
//					separator: '\n',
//					process: function( src, filepath ) {
//						return '// Source: ' + filepath.replace( BUILD_DIR, '' ) + '\n' + src;
//					}
//				},
//				src: [
//					BUILD_DIR + 'gb/js/tinymce/tinymce.min.js',
//					BUILD_DIR + 'gb/js/tinymce/themes/modern/theme.min.js',
//					BUILD_DIR + 'gb/js/tinymce/plugins/*/plugin.min.js'
//				],
//				dest: BUILD_DIR + 'gb/js/tinymce/gb-tinymce.js'
//			}
		},
		compress: {
//			tinymce: {
//				options: {
//					mode: 'gzip',
//					level: 9
//				},
//				src: '<%= concat.tinymce.dest %>',
//				dest: BUILD_DIR + 'gb/js/tinymce/gb-tinymce.js.gz'
//			}
		},
		jsvalidate:{
			options: {
				globals: {},
				esprimaOptions:{},
				verbose: false
			},
			build: {
				files: {
					src: [
						BUILD_DIR + '/**/*.js',
						'!' + BUILD_DIR + '/gb-content/**/*.js'
					]
				}
			}
		},
		imagemin: {
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				src: [
					'gb{,-admin}/images/**/*.{png,jpg,gif,jpeg}'
				],
				dest: SOURCE_DIR
			}
		},
		watch: {
			all: {
				files: [
					SOURCE_DIR + '**',
					// Ignore version control directories.
					'!' + SOURCE_DIR + '**/.{svn,git}/**'
				],
				tasks: ['clean:dynamic', 'copy:dynamic'],
				options: {
					dot: true,
					spawn: false,
					interval: 2000
				}
			},
			rtl: {
				files: [
					SOURCE_DIR + 'gb-admin/css/*.css',
					SOURCE_DIR + 'gb/css/*.css'
				],
				tasks: ['cssjanus:dynamic'],
				options: {
					spawn: false,
					interval: 2000
				}
			},
			test: {
				files: [
					'tests/qunit/**',
					'!tests/qunit/editor/**'
				],
				tasks: ['qunit']
			}
		}
	});

	// Register tasks.

	// RTL task.
	grunt.registerTask('rtl', ['cssjanus:core']);

	// JSHint task.
	grunt.registerTask('jshint:corejs', ['jshint:grunt', 'jshint:tests', 'jshint:themes', 'jshint:core']);

	// Pre-commit task.
	grunt.registerTask('precommit', 'Runs front-end dev/test tasks in preparation for a commit.',
		['autoprefixer:core', 'imagemin:core', 'jshint:corejs', 'qunit:compiled']);

	// Copy task.
	grunt.registerTask('copy:all', ['copy:files', 'copy:gb-admin-rtl', 'copy:version']);

	// Build task.
	grunt.registerTask('build', ['clean:all', 'copy:all', 'sass:core', 'cssmin:core', 'rtl', 'cssmin:rtl',
		'uglify:core', 'jsvalidate:build']);

	// Testing tasks.
	grunt.registerMultiTask('phpunit', 'Runs PHPUnit tests, including the ajax and multisite tests.', function() {
		grunt.util.spawn({
			cmd: this.data.cmd,
			args: this.data.args,
			opts: {stdio: 'inherit'}
		}, this.async());
	});

	grunt.registerTask('qunit:compiled', 'Runs QUnit tests on compiled as well as uncompiled scripts.',
		['build', 'copy:qunit', 'qunit']);

	grunt.registerTask('test', 'Runs all QUnit and PHPUnit tasks.', ['qunit:compiled', 'phpunit']);

	// Travis CI tasks.
	grunt.registerTask('travis:js', 'Runs Javascript Travis CI tasks.', [ 'jshint:corejs', 'qunit:compiled' ]);
	grunt.registerTask('travis:phpunit', 'Runs PHPUnit Travis CI tasks.', 'phpunit');

	// Default task.
	grunt.registerTask('default', ['build']);

	// Add a listener to the watch task.
	//
	// On `watch:all`, automatically updates the `copy:dynamic` and `clean:dynamic`
	// configurations so that only the changed files are updated.
	// On `watch:rtl`, automatically updates the `cssjanus:dynamic` configuration.
	grunt.event.on('watch', function( action, filepath, target ) {
		if ( target !== 'all' && target !== 'rtl' ) {
			return;
		}

		var relativePath = path.relative( SOURCE_DIR, filepath ),
			cleanSrc = ( action === 'deleted' ) ? [relativePath] : [],
			copySrc = ( action === 'deleted' ) ? [] : [relativePath];

		grunt.config(['clean', 'dynamic', 'src'], cleanSrc);
		grunt.config(['copy', 'dynamic', 'src'], copySrc);
		grunt.config(['cssjanus', 'dynamic', 'src'], copySrc);
	});
};
