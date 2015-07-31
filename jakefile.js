/*
  jakefile.js for pmwiki-list-categories

  */

var open = require('open'),
    config = require('./config'),
    pkgjson = require('./package.json'),
    releaseTools = require('releasetools');


// task('default', ['push']); // if I could figure out a way to pass a parameter here, I would

desc('dump');
task('dump', [], function() {
    console.log(config);
});

desc('Push the project (no ignore) to the config location passed in.\nUsage: jake push[test|main]');
task('push', [], function (location) {

    if (! config.target.hasOwnProperty(location)) {
        console.error(location + ' is not a valid location. Try one of the following:');
        console.log(config.target);
        return;
    }
    console.log(config.target[location]);
    push(config.target[location]);
    });


var push = function(target) {

    var path = require("path"),
        fs = require("fs");

    var copy = function(file) {
        var dest = path.join(target, path.dirname(file));
        jake.mkdirP(dest);
        jake.cpR(file, dest); // although this is reccursive, if the directory doesn't exist... creates a file of the same name. hunh.
    };

    getProjectFiles().toArray().map(copy);

    };


desc('Open remote repo in browser');
task('openrepo', [], function() {
    open(config.remote);
});



desc('Zip up the project.');
task('zip', [], function() {

    var name = 'pmwiki-list-categories';

    // TODO: mmmmmaybe we should use dates?
    var version = pkgjson.version;

    // NOTE: 0.4.4 is last known "working" version on windows
    // as of 2015.07.30
    var AdmZip = require('adm-zip');
    var zip = new AdmZip();


    var addFile = function(file) {

        var path = file.substring(0, file.lastIndexOf('/') + 1);

        // console.log('path: ' + path + ' file: ' + file);

        zip.addLocalFile(file, path);

    };

    getProjectFiles().toArray().map(addFile);

    zip.writeZip(name + '.' + version + '.zip');


});


var getProjectFiles = function() {

    var list = new jake.FileList();

    list.exclude(/.*bak.*/);

    list.include('*.php');

    // console.log(list);

    return list;
};

// switching to semantic-versioning, from the package.json file?
// or not. who knows.
var getDateFormatted = function() {
    var d = new Date();
    var df = d.getFullYear() + '.' + pad((d.getMonth() + 1), 2) + '.' + pad(d.getDate(), 2);
    return df;
};

var pad = function(nbr, width, fill) {
    fill = fill || '0';
    nbr = nbr + '';
    return nbr.length >= width ? nbr : new Array(width - nbr.length + 1).join(fill) + nbr;
};

var tempname = function() {
    return "build"; // that will do for now....
};

desc('Bump version in package.json');
task('bump', function(releaseType) {
    releaseType = releaseType || 'patch';
    console.log('Bumping version in package.json...');
    releaseTools.updateVersionInPackageJson(releaseType, function(err, oldVersion, newVersion) {
        if (err) {
            fail('Error while updating version in package.json: ' + err);
        }
        console.log(oldVersion + ' --> ' + newVersion);
        console.log('Done!');
        complete();
    });
}, true);
