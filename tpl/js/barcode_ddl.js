jQuery(function ($){
	/**
	 * use dispSvitemAdminBarcodeMgmt
	 **/
	$('._deleteDdl').click(function (event){
		event.preventDefault();
		if(!confirm('삭제하시겠습니까?')) 
			return;

		var tr = $(event.target).parent().parent();
		tr.remove();
	});
	$('._addDdl').click(function (event){
		var $tbody = $('._groupList');
		var index = 'new'+ new Date().getTime();

		$tbody.find('._template').clone(true)
			.removeClass('_template')
			.find('input').removeAttr('disabled').end()
			.show()
			.appendTo($tbody)
			.find('.lang_code').xeApplyMultilingualUI();
		return false;
	});
});