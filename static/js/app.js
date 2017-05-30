(function(){
	'use strict';

	var dwnApp = angular.module('dwnApp', [
		'ui.router',
		'dwnAppControllers',
		'angularUtils.directives.dirPagination',
		'angular-loading-bar',
		'angular.filter',
		'masonry'
	]);

	dwnApp.config(['$stateProvider','$urlRouterProvider',function($stateProvider,$urlRouterProvider) {
		$urlRouterProvider.otherwise("/torrents/t9/list/series/1");

		$stateProvider
		.state('torrents', {
			url			: '/torrents/',
			templateUrl	: 'partials/torrents.html'
		})
		.state('torrents.list', {
			url			: "{provider}/{action}/{type}/{page}?query",
			params		: {
				query		: ''
			},
			templateUrl	: 'partials/list.html',
			controller	: 'listCtrl'
		})
		.state('home', {
			url			: "/home",
			templateUrl	: 'partials/home.html',
			controller	: 'HomeCtrl'
		})
	}]);
})()
