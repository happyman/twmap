
module.exports = function(grunt) {
  grunt.initConfig({
    //copy: {
    //  generated: {
	//files: [
    //    { cwd: 'twmap_gen/', src: ['**'], dest: 'dist/twmap_gen',expand: true },
	//{ cwd: 'twmap3/', src: ['**'], dest: 'dist/twmap3',expand: true } 
	//]
    //  },
    //},
	clean: {
          files: [
            'dist/twmap_gen/js/main.*.js',
            'dist/twmap_gen/css/main.*.css',
            'dist/twmap3/js/vender.*.js',
            'dist/twmap3/css/twmap3.*.css'
          ]
	},
	rsync: {
		options: {
			args: ["-av"],
			exclude: [".git*","*.scss","node_modules"],
			recursive: true
		},
		generated: {
			options: {
			src: ['twmap_gen','twmap3'], 
			dest: 'dist/'
			}
		}
	},
    filerev: {
      options: {
        encoding: 'utf8',
        algorithm: 'md5',
        length: 20
      },
      source: {
        files: [{
          src: [
            'dist/twmap_gen/js/main.js',
            'dist/twmap_gen/css/main.css',
            'dist/twmap3/js/vender.js',
            'dist/twmap3/css/twmap3.css'
          ]
        }]
      }
    },
pkg: grunt.file.readJSON('package.json'),
    uglify: {
        options: {
           banner: '/*! <%= pkg.name %> - v<%= pkg.version %> - ' +
            '<%= grunt.template.today("yyyy-mm-dd") %> \n' + grunt.file.read('buddha.txt') + '\n*/'
        }
    },
    useminPrepare: {
	'html-twmap':{
	src:  ['twmap_gen/pages/header.html', 'twmap_gen/pages/main.html' ],
     options: {
        dest: 'dist/twmap_gen',
	root: 'twmap_gen',
	type: 'html'
      }
	},
	'html-twmap3': {
	src:  ['twmap3/index.php' ],
	options: {
	  dest: 'dist/twmap3',
	  root: 'twmap3',
	  type: 'html'
	}
	}
    },
    usemin: {
      html: ['dist/twmap_gen/pages/header.html','dist/twmap_gen/pages/main.html', 'dist/twmap3/index.php' ],
      options: {
        assetsDirs: ['dist/twmap_gen', 'dist/twmap3']
      }
    },
    jshint: {
	all: [ 'twmap3/js/main.js', 'twmap3/js/functions.js', 'twmap_gen/js/twmap.js' ]
    },
phplint: {
    options: {
        swapPath: '/tmp'
    },
    all: [ 'twmap3/**/*.php', 'twmap_gen/**/*.php' ]
	}
  });

  grunt.loadNpmTasks('grunt-usemin');
  //grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-rsync');
  grunt.loadNpmTasks('grunt-contrib-cssmin');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-filerev');
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.loadNpmTasks('grunt-phplint');
  grunt.loadNpmTasks('grunt-contrib-clean');

  grunt.registerTask('default', [
      'jshint',
      'phplint',
      'clean',
     // 'copy:generated',
	  'rsync:generated',
      'useminPrepare',
      'concat',
      'uglify',
      'cssmin',
      'filerev',
      'usemin',
  ]);
};

