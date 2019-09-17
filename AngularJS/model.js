(function() {
	'use strict';

	/**
	*
	* @script model
	*/
	var scriptModel = function($http, $q, API_PREFIX_V1, $resource){ 

		
		var _script = {};

		/**
		* @Request Transformer
		* @param [data object]
		**/
		_script.transform = function(data) {
			return $.param(data);
		};


		/**
		*
		* @get script list
		* @param [resource bool]
		* @param [query object]
		*/
		_script.getList = function(resource, query) {
			
			if(resource == true) {

				return $resource( API_PREFIX_V1+'/scripts' );
			}

			if( !angular.isDefined(query) ) {
				var query = {};
			} 

			var _defr = $q.defer();

			/**
			*
			* @send Mail
			*/
			$http.get(API_PREFIX_V1+'/scripts', {
				params: query,
				ignoreLoadingBar: true
			})
			.then(function(success) {
				_defr.resolve(success);
			}, function(error) {
				_defr.reject(error);
			})

			return _defr.promise;
		};

		/**
		*
		* @save script
		* @param [query object]
		*/
		_script.store = function(params) {
			// 
			var _defr = $q.defer();

			/**
			*
			* @send Mail
			*/
			$http.post(API_PREFIX_V1+'/scripts',
				params, 
				{
					headers: { 
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					transformRequest: _script.transform
			}).then(function(success) {
				_defr.resolve(success);
			}, function(error) {
				_defr.reject(error);
			})

			return _defr.promise;
		};

		/**
		*
		* @update script
		* @param [id init]
		* @param [query object]
		*/
		_script.update = function(id, params) {
			// 
			var _defr = $q.defer();

			/**
			*
			* @send Mail
			*/
			$http.put(API_PREFIX_V1+'/scripts/'+id,
				params, 
				{
					headers: { 
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					transformRequest: _script.transform
			}).then(function(success) {
				_defr.resolve(success);
			}, function(error) {
				_defr.reject(error);
			})

			return _defr.promise;
		};

		/**
		*
		* @get script
		* @param [id init]
		* @param [query object]
		*/
		_script.get = function(id, query) {
			
			if( !angular.isDefined(query) ) {
				var query = {};
			} 

			var _defr = $q.defer();

			/**
			*
			* @send Mail
			*/
			$http.get(API_PREFIX_V1+'/scripts/'+id, query)
			.then(function(success) {
				_defr.resolve(success);
			}, function(error) {
				_defr.reject(error);
			})

			return _defr.promise;
		};

		/**
		*
		* @delete script
		* @param [id init]
		*/
		_script.delete = function(id) {
			
			var _defr = $q.defer();

			/**
			*
			* @send Mail
			*/
			$http.delete(API_PREFIX_V1+'/scripts/'+id, {
				ignoreLoadingBar: true
			})
			.then(function(success) {
				_defr.resolve(success);
			}, function(error) {
				_defr.reject(error);
			})

			return _defr.promise;
		};

		return _script;
	};

	/**
	*
	* @inject dependencys
	*/
	scriptModel.$inject = ['$http', '$q', 'API_PREFIX_V1', '$resource'];

	/**
	* Job Progress Module
	*
	* @Model Email
	*/
	angular
		.module('jobProgress')
		.factory('Scripts', scriptModel);

})();