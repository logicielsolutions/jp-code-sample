(function() {
	'use strict';

	/**
	* 
	* @derective function
	*/
	var folderContextMenuDirectve = function($timeout) {
		return {
			restrict: 'A',
			replace: true,
			controller :['$scope', '$element', '$attrs', 
			function($scope, $element, $attrs){
							
				$element.on("contextmenu", function (e) {

			        e.preventDefault();		        	
					
					if( $attrs.islocked == 1 || $attrs.islocked == '1') {
						return;
					};

		            $.contextMenu({
		                selector: '#'+$attrs.id,
		                className: 'directory-icon-list',
		                position: function(opt, x, y){
					        opt.$menu.css({top: y-25, left: x});
					    },	
		                callback: function() {
		                	
		                	$('#'+$attrs.id).contextMenu("hide");

		                	// Destroy Old Menu
		                	$timeout(function() {
		                		$('#'+$attrs.id).contextMenu("destroy");
		                	}, 300)
		                },
		                // popup item add
		                items: $.contextMenu.fromMenu($($attrs.idRef)),
			        });
		            
					var e = angular.element('div.left-drop-icon');
					if( e.hasClass('open') ) {
						e.removeClass('open');
						$element.removeClass('proposal-template-border');
					};
				}); 

				$scope.$on('$destroy', function() {
					$($element).unbind();
					$.contextMenu('destroy')
				})
			}],	
		}
	};
	/**
	* 
	* @Dependency
	*/
	folderContextMenuDirectve.$inject = ['$timeout'];

	/**
	* @Directive
	*/
	angular
		.module('folder.icon',[])
		.directive('folderContextmenu', folderContextMenuDirectve);
})();