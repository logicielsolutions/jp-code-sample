(function() {
	'use strict';

	var Modal = function ($modalInstance, $injector, ItemData, ShowAddForm) {
		var viewData = this;

		/**
		*
		* [@method init]
		* [@desction execute onload]
		*/
		var init = function() {

			viewData.frm = {};

			viewData.item = angular.copy( JobProgress.getObject(ItemData));
			viewData.item.list = JobProgress.getArray(viewData.item.list);

			viewData.addEditForm =  ShowAddForm;


			/**
			* @Stages Sorting Options
			*/
			/**
			* @Stages Sorting Options
			*/
			viewData.sortOptions = {
				'handle': 'span.tier-move'
			};
		};

		/**
		*
		* [@method addItem]
		* [@desction Add new item in list]
		*/
		viewData.addItem = function() {
			var obj  = angular.copy(viewData.frm);

			viewData.frm = {};

			if( JobProgress.haveValue(obj.id)) {
				console.log( obj );
				viewData.item.list[obj.index].name = obj.name;
				return;
			}

			if( JobProgress.haveValue(obj.name) ) {
				viewData.item.list.push(obj);
			}
		};

		/**
		*
		* [@method dismiss]
		* [@desction close the modal]
		*/
		viewData.dismiss = function() {
			$modalInstance.dismiss();
		};

		/**
		*
		* [@method editItem]
		* [@desction get data for edit list]
		*/
		viewData.editItem = function(item, index, editMode) {

			if( !JobProgress.isTrue(editMode) ) {
				viewData.frm = {};
				return false;
			}

			viewData.frm = {
				id: new Date().getTime(),
				index: index,
				name: angular.copy(item.name)
			};

		};

		/**
		*
		* [@method apply]
		* [@desction save the entered data]
		*/
		viewData.apply = function() {
			$modalInstance.close(viewData.item.list);
		};

		/**
		*
		* [@method delete]
		* [@desction save the entered data]
		*/
		viewData.deleteItem = function(item, index) {
			viewData.item.list.splice(index, true);
		};


		$injector.invoke(init);
	};

	Modal.$inject = ['$modalInstance', '$injector', 'ItemData', 'ShowAddForm']

	/**
	* jobProgress Module
	*
	* Description
	*/
	angular
		.module('jobProgress')
		.controller('TemplateMultiChoiceModal', Modal);
})();