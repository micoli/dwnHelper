(function(){
	'use strict';


	// Déclaration des contrôleurs
	var dwnAppControllers = angular.module('dwnAppControllers', []);

	dwnAppControllers
	.directive('onKeyEnterUp', function() {
		return function(scope, elm, attrs) {
			elm.bind("keyup", function(e) {
				if(e.code=="Enter"){
					scope.$apply(attrs.onKeyEnterUp);
				}
			});
		};
	});

	dwnAppControllers
	.controller('HomeCtrl', ['$scope','$http',function($scope,$http) {
		$scope.downloads = [];
		$scope.state = 'loading';

		$http({
			method	: 'GET',
			url		: '/api/torrents/downloads'
		}).then(function successCallback(response) {
			$scope.state = 'loaded';
			$scope.downloads = response.data
		}, function errorCallback(response) {
			$scope.state = 'error';
		});
	}]);

	dwnAppControllers
	.controller('torrentCtrl', ['$rootScope','$scope','$http','$stateParams','$state',function ($rootScope,$scope,$http,$stateParams,$state) {
		$rootScope.radioModel={
			dst :'local'
		};

		$scope.checkboxModel = {
			'french': true,
			'new'	: true
		};
	}]);

	dwnAppControllers
	.controller('listCtrl', ['$rootScope','$scope','$http','$stateParams','$state','$sce',function ($rootScope,$scope,$http,$stateParams,$state,$sce) {
		$scope.torrents = [];
		var page = $stateParams.page;
		$scope.query = $stateParams.query;
		$scope.provider	= $stateParams.provider;
		$scope.type	= $stateParams.type;
		$scope.action = $stateParams.action;
		$scope.type = $stateParams.type;
		$scope.totalItems = 0;

		$scope.pagination = {
				current: page
		};

		$scope.pageChangeHandler = function(newPage) {
			$state.go("torrents.list", {
				provider	: $stateParams.provider,
				action		: $stateParams.action,
				type		: $stateParams.type,
				page		: newPage,
				query		: ''
			},{
				inherit		: false
			});
		};

		$scope.search=function(){
			$state.go("torrents.list", {
				provider	: $stateParams.provider,
				action		: 'search',
				type		: $stateParams.type,
				query		: $scope.query,
				page		: 1
			},{
				inherit		: false
			});
		}

		$scope.launchTorrent =function(item,version) {
			$http({
				method	: 'GET',
				url		: '/api/torrents/dwn/'+$scope.radioModel.dst+'/'+$stateParams.provider+'/'+item.mtype+'/'+version.hash
			});
			version.downloaded=true;
			item.downloaded=true;
		}

		$scope.showDetail = function(item) {
			item.showdetail=!item.showdetail
			console.log(item.showdetail);
			if(item.detail.state=='todo'){
				item.detail.state='loading';
				$http({
					method	: 'GET',
					url		: '/api/torrents/detail/'+$stateParams.provider+'/'+item.detail.hash
				}).then(function successCallback(response) {
					item.detail.state = 'loaded';
					item.detail.desc = $sce.trustAsHtml(response.data.desc);
				}, function errorCallback(response) {
					item.detail.state='error';
				});
			}
		}
		$scope.markTorrent = function(item,version) {
			$http({
				method	: 'GET',
				url		: '/api/torrents/mark/'+$stateParams.provider+'/'+version.hash
			});
			version.downloaded=true;
			item.downloaded=true;
		}

		$scope.mother=$scope;

		$scope.getResultsPage = function(page,force){
			page = page || $scope.pagination.current;
			force = force|| false;
			$scope.torrents = [];
			$http({
				method	: 'GET',
				url		: '/api/torrents/'+$stateParams.provider+'/'+$stateParams.action+'/'+$stateParams.type+'?page='+page+($stateParams.query?'&query='+$stateParams.query:'')+($stateParams.subtype?'&subtype='+$stateParams.subtype:'')+'&force='+(force?1:0)
			}).then(function successCallback(response) {
				$scope.totalItems	= response.data.totalItems;
				$scope.rootUrl		= response.data.rootUrl;
				$scope.torrents		= response.data.items;
				angular.forEach($scope.torrents,function(v){
					v.showdetail=false;
				})
			}, function errorCallback(response) {
				$scope.torrents = [];
			});
		}

		$scope.getResultsPage(page);
	}]);
})()