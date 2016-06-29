'use strict';

var app = angular.module('pdf2web', ['angularFileUpload']);

app.controller('UploadFile', function($scope, $upload, $timeout) {

    $scope.isUploading = false;
    $scope.isConverting = false;

    $scope.onFileSelect = function($files) {

        for (var i = 0; i < $files.length; i++) {
            var file = $files[i];
            $scope.upload = $upload.upload({
                url: 'action/upload',
                file: file // or list of files ($files) for html5 only
                //fileName: 'doc.jpg' or ['1.jpg', '2.jpg', ...] // to modify the name of the file(s)
                // customize file formData name ('Content-Disposition'), server side file variable name.
                //fileFormDataName: myFile, //or a list of names for multiple files (html5). Default is 'file'
                // customize how data is added to formData. See #40#issuecomment-28612000 for sample code
                //formDataAppender: function(formData, key, val){}
            }).progress(function(evt) {
                $scope.progress = parseInt(100.0 * evt.loaded / evt.total);
                $scope.isUploading=true;

                if(evt.loaded == evt.total){
                    $timeout(function(){
                        $scope.isConverting=true;
                    }, 1000);
                }

            }).success(function(data, status, headers, config) {
                $scope.$emit('onFileUploaded');

                $scope.isUploading=false;
                $scope.isConverting=false;
            });
            //.error(...)
            //.then(success, error, progress);
            // access or attach event listeners to the underlying XMLHttpRequest.
            //.xhr(function(xhr){xhr.upload.addEventListener(...)})
        }
    };
});

app.controller('ListFolders', function($scope, $http){

    $scope.listDirs = function(){
        $http.get('/action/listDirs').then(function(resp) {
            $scope.folders = resp.data;
        });
    };

    $scope.$on('onFileUploaded', function () {
        $scope.listDirs();
    });

    $scope.onDelete = function ($key) {
        $http.get('/action/delete/'+ $key).then(function(resp) {
            $scope.listDirs();
        });
    };
});